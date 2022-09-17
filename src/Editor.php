<?php

namespace davidxu\weditor;

use davidxu\base\assets\QETagAsset;
use davidxu\base\enums\QiniuUploadRegionEnum;
use davidxu\base\enums\UploadTypeEnum;
use davidxu\base\helpers\StringHelper;
use davidxu\config\helpers\ArrayHelper;
use davidxu\weditor\assets\EditorAsset;
use davidxu\weditor\assets\EditorPluginImageModalAsset;
use davidxu\weditor\assets\EditorPluginLinkCardAsset;
use davidxu\weditor\assets\EditorPluginUploadAttachmentAsset;
use davidxu\weditor\assets\QiniuJsAsset;
use Qiniu\Auth;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;
use davidxu\base\widgets\InputWidget;
use Yii;

/**
 * Editor Class
 */
class Editor extends InputWidget
{
    /** @var array */
    public $clientOptions = [
        'toolbarConfig' => [],
        'hoverbarKeys' => [],
        'menuConfig' => [],
    ];

    public $enablePluginUploadAttachment = false;
    public $enablePluginLinkCard = false;
    public $enablePluginImageModal = false;

    private $_editorWrapper;
    private $_editorToolbarContainer;
    private $_editorContainer;
    private $_optionsId;

    private $_encodedClientOptions;
    private $_encodedMetaData;

    private $_encodedEditorHoverbarKeys;
    private $_encodedEditorMenuConfig;
    private $_encodedToobarConfig;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        $_view = $this->getView();
        parent::init();
        $this->options['class'] = 'form-control';
        if (!$this->hasModel()) {
            $this->options['id'] = $this->getFieldId();
        }
        $this->_optionsId = StringHelper::camelize($this->options['id'], '-');
        $this->_editorWrapper = $this->options['id'] . '_editor-wrapper';
        $this->_editorContainer = $this->options['id'] . '_editor-contianer';
        $this->_editorToolbarContainer = $this->options['id'] . '_editor-toolbar-contianer';

        if (!isset($this->clientOptions['lang']) && Yii::$app->language !== 'en-US') {
            $this->clientOptions['lang'] = Yii::$app->language;
//            $this->clientOptions['lang'] = substr(Yii::$app->language, 0, 2);
        }
        $this->registerAssets($_view);

        if ($this->drive === UploadTypeEnum::DRIVE_QINIU) {
            $this->metaData = ArrayHelper::merge([
                'x:store_in_db' => (string)$this->storeInDB,
                'x:member_id' => Yii::$app->user->isGuest ? '0' : (string)(Yii::$app->user->id),
                'x:upload_ip' => (string)(Yii::$app->request->remoteIP),
            ], $this->metaData);
        }
        if ($this->drive === UploadTypeEnum::DRIVE_LOCAL) {
            $this->metaData['file_field'] = $this->name;
            $this->metaData['store_in_db'] = $this->storeInDB;
            if (Yii::$app->request->enableCsrfValidation) {
                $this->metaData[Yii::$app->request->csrfParam] = Yii::$app->request->getCsrfToken();
            }
        }
        $this->_encodedMetaData = Json::encode($this->metaData);
        $customUploadJs = /** @lang JavaScript */ <<< CUSTOM_UPLOAD_JS
({
    async customUpload(file, insertFile) {
        if ({$this->secondUpload} || {$this->secondUpload} === 'true') {
            secondUploadFile(file, insertFile).then(res => {
                if (!res) {
                    handleUploadDrive(file, insertFile)
                }
            })
        } else {
            handleUploadDrive(file, insertFile)
        }
    }
})
CUSTOM_UPLOAD_JS;

        $this->clientOptions['menuConfig'] = ArrayHelper::merge(
            $this->clientOptions['menuConfig'],
            [
                'uploadImage' => new JsExpression($customUploadJs),
                'uploadVideo' => new JsExpression($customUploadJs),
            ]
        );
        if ($this->enablePluginUploadAttachment) {
            $this->clientOptions['menuConfig'] = ArrayHelper::merge(
                $this->clientOptions['menuConfig'],
                [
                    'uploadAttachment' => new JsExpression($customUploadJs),
                ]
            );
        }
        $this->registerScripts($_view);
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $html = [];
        $html[] = Html::beginTag('div', [
            'id' => $this->_editorWrapper,
            'class' => 'editor—wrapper',
        ]);
        if ($this->hasModel()) {
            $html[] = Html::activeTextarea(
                $this->model,
                $this->attribute,
                ArrayHelper::merge(
                    $this->options,
                    ['class' => 'd-none']
                )
            );
        } else {
            $html[] = Html::textarea(
                $this->name,
                $this->value,
                ArrayHelper::merge($this->options, ['class' => 'd-none'])
            );
        }
        $html[] = Html::tag('div', null, [
            'id' => $this->_editorToolbarContainer,
            'class' => 'editor-toolbar-container',
        ]);
        $html[] = Html::tag('div', null, [
            'id' => $this->_editorContainer,
            'class' => 'editor-container'
        ]);
        $html[] = Html::endTag('div');
        return implode("\n", $html);
    }

    private function registerAssets($_view)
    {
        EditorAsset::register($_view);
        if ((bool)$this->isQiniuDrive()) {
            QiniuJsAsset::register($_view);
        }
        if ($this->secondUpload) {
            QETagAsset::register($_view);
        }
        $this->registerPlugins($_view);
    }

    private function getFieldId()
    {
        return $this->hasModel() ? Html::getInputId($this->model, $this->attribute) : StringHelper::getInputId($this->name);
    }

    private function registerScripts($_view)
    {
        // common config before editor initialized
        $this->registerPreScripts($_view);

        $editor = $toolbarInsertKeys = [];
        // import editor
        $editor[] = new JsExpression('const { createEditor, createToolbar, Boot } = window.wangEditor');

        // config editor
        if ($this->enablePluginUploadAttachment) {
            $editor[] = new JsExpression('Boot.registerModule(window.WangEditorPluginUploadAttachment.default)');
            $this->clientOptions['toolbarConfig'] = ArrayHelper::merge(
                $this->clientOptions['toolbarConfig'],
                [
                    'insertKeys' => [
                        'index' => 23,
                        'keys' => ['uploadAttachment'],
                    ],
                ],
            );

            $this->clientOptions['hoverbarKeys'] = ArrayHelper::merge($this->clientOptions['hoverbarKeys'], [
                'attachment' => [
                    'menuKeys' => ['downloadAttachment'],
                ],
            ]);
        }

        $this->_encodedEditorHoverbarKeys = Json::encode($this->clientOptions['hoverbarKeys'] ?? []);
        $this->_encodedEditorMenuConfig = Json::encode($this->clientOptions['menuConfig'] ?? []);

        $editorConfigJs = /** @lang JavaScript */ <<< EDITOR_CONFIG_JS
const {$this->_optionsId}EditorConfig = {
    // placeholder: 'Type here...',
    MENU_CONF: {$this->_encodedEditorMenuConfig},
    hoverbarKeys: {$this->_encodedEditorHoverbarKeys},
    onCreated(editor) {
        editor.clear()
        editor.dangerouslyInsertHtml($('#{$this->options["id"]}').val())
    },
    onChange(editor) {
        const html = editor.getHtml()
        $('#{$this->options["id"]}').val(html)
    }
}
EDITOR_CONFIG_JS;
        $editor[] = new JsExpression($editorConfigJs);

        // create editor based on editor config
        $editorJs = /** @lang JavaScript */ <<< EDITOR_CREATOR_JS
const {$this->_optionsId}Editor = createEditor({
    selector: '#{$this->_editorContainer}',
    html: null,
    config: {$this->_optionsId}EditorConfig,
    mode: 'default', // or 'simple'
})
EDITOR_CREATOR_JS;
        $editor[] = new JsExpression($editorJs);

        // Toolbar config
        $this->_encodedToobarConfig = Json::encode($this->clientOptions['toolbarConfig'] ?? []);
        $editorToobarConfigJs = /** @lang JavaScript */ <<< EDITOR_TOOLBAR_CONFIG_JS
const {$this->_optionsId}ToolbarConfig = {$this->_encodedToobarConfig}
EDITOR_TOOLBAR_CONFIG_JS;
        $editor[] = new JsExpression($editorToobarConfigJs);

        // create editor toolbar based on toolbar config
        $editorToolbarJs = /** @lang JavaScript */ <<< EDITOR_TOOLBAR_CREATOR
const {$this->_optionsId}EditorToolbar = createToolbar({
    editor: {$this->_optionsId}Editor,
    selector: '#{$this->_editorToolbarContainer}',
    config: {$this->_optionsId}ToolbarConfig,
    mode: 'default', // or 'simple'
})
EDITOR_TOOLBAR_CREATOR;
        $editor[] = new JsExpression($editorToolbarJs);

        $editorJs = implode("\n", $editor);
        $_view->registerJs($editorJs);
    }

    private function registerPreScripts($view)
    {
        $additinal = [];
        if ($this->secondUpload) {
            $additinal[] = /** @lang JavaScript */ <<< CALCULATE_FILE_HASH
const getHash = function (file) {
    return new Promise(function(resolve, reject) {
        let hash = ''
        let reader = new FileReader()
        reader.readAsArrayBuffer(file)
        reader.onload = () => {
            hash = getEtag(reader.result)
            resolve(hash)
        }
    })
}
CALCULATE_FILE_HASH;
            $additinal[] = /** @lang JavaScript */ <<< GET_INFO_BY_HASH
function secondUploadFile(file, insertFile) {
    const promise = new Promise(resolve => {
        getHash(file).then(function (hash) {
            let formData = new FormData()
            $.each({$this->_encodedMetaData}, function (key, value) {
                formData.append(key,value)
            })
            formData.delete('file_field')
            formData.delete('store_in_db')
            formData.append('hash', hash)
            $.ajax({
                url: '{$this->getHashUrl}',
                data: formData,
                type: 'POST',
                dataType: 'json',
                contentType:false,
                processData:false,
                success: function (response) {
                        if (response.result || response.result === 'true') {
                            if (response.result.file_type === 'images') {
                                insertFile(response.result.path, response.result.name, response.result.path)
                            } else if (response.result.file_type === 'videos') {
                                insertFile(response.result.path, response.result.poster)
                            }
                            resolve(true)
                        } else {
                            resolve(false)
                        }
                }
            })
        })
    })
return promise
}
GET_INFO_BY_HASH;
        }

        $additinal[] = /** @lang JavaScript */ <<< COMMON_JS

sweetAlertToast = Swal.mixin({
    showConfirmButton: false,
    backdrop: `rgba(0, 0, 0, 0.8)`,
    title: '<i class="fas fa-spinner fa-pulse"></i>',
})

function generateKey(length) {
    length = length || 32;
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_';
    let maxPos = chars.length;
    let str = ''
    for (i = 0; i < length; i++) {
        str += chars.charAt(Math.floor(Math.random() * maxPos));
    }
    return str
}

function progressBody(percent, progress_class) {
    if (progress_class === '' || progress_class === null || typeof progress_class === 'undefined') {
        progress_class = 'progress-bar-animated progress-bar-striped bg-info'
    }
    return [
        '<div class="progress">',
        '<div class="progress-bar ',
        progress_class,
        ' " ',
        'role="progressbar" aria-valuenow="',
        percent,
        '" aria-valuemin="0" aria-valuemax="100" style="width: ' ,
        percent,
        '%"> ',
        percent,
        '% </div>',
        '</div>',
    ].join('')
}
COMMON_JS;

        $additinal[] = /** @lang JavaScript */ <<< HANDLE_UPLOAD_DRIVE
function handleUploadDrive(file, insertFile) {
    if ({$this->isQiniuDrive()}) {
        uploadFilesToQiniu(file, insertFile)
    } else if ({$this->isLocalDrive()}) {
        uploadFilesToLocal(file, insertFile)
    } else {
        sweetAlertToast.update({
            toast: true,
            position: 'top-end',
            html: '',
            title: 'Something with wrong',
            icon: 'error'
        })
    }
}
HANDLE_UPLOAD_DRIVE;

        $additinal[] = /** @lang JavaScript */ <<< GET_FILE_INFO
function getFileInfo(file) {
    const mimeType = (file.type.split('/', 1)[0]).toLowerCase()
    let fileType = 'others'
    if (mimeType === 'image') {
        fileType = 'images'
    } else if (mimeType === 'video') {
        fileType = 'videos'
    } else if (mimeType === 'audio') {
        fileType = 'audios'
    }
    const extension = (file.name.substr(file.name.lastIndexOf('.'))).toLowerCase()
    const key = '{$this->uploadBasePath}' + fileType + '/' + generateKey() + extension
    return {
        name: file.name,
        extension: extension,
        key: key,
        size: file.size,
        mime_type: file.type,
        file_type: fileType
    }
}
GET_FILE_INFO;

        $additinal[] = /** @lang JavaScript */ <<< UPLOAD_FILE_TO_LOCAL
function uploadFilesToLocal(file, insertFile) {
    sweetAlertToast.fire({
        allowEscapeKey: false,
    })
    
    const blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice
    const fileInfo = getFileInfo(file)
    const chunkFileKey = (fileInfo.key).replace(/\//g, '_').replace(/\./g, '_')
    let chunkSize = parseInt('{$this->chunkSize}')
    const totalChunks = Math.ceil(file.size / chunkSize)
    let currentChunkIndex = 0
    let formData = new FormData()
    $.each({$this->_encodedMetaData}, function (key, value) {
        formData.append(key,value)
    })
    formData.append('size', fileInfo.size)
    formData.append('extension', fileInfo.extension)
    formData.append('chunk_key', chunkFileKey)
    formData.append('total_chunks', totalChunks)
    //upload
    const _sendFile = (currentChunkIndex) => {
        const start = currentChunkIndex * chunkSize;
        const end = Math.min(file.size, start + chunkSize);
        formData.append('chunk_index', currentChunkIndex)
        if (currentChunkIndex < totalChunks) {
            formData.append('file', blobSlice.call(file, start, end))
        }
        $.ajax({
            url: '{$this->url}',
            data: formData,
            type: 'POST',
            dataType: 'json',
            contentType:false,
            processData:false,
            xhr:function() {
                let myXhr = $.ajaxSettings.xhr()
                if (myXhr.upload) {
                    myXhr.upload.addEventListener('progress',function(e) {
                        // let percent = (100 * e.loaded / e.total).toFixed(2)
                        let percent = (100 * e.loaded / file.size).toFixed(0)
                        sweetAlertToast.update({
                            html: progressBody(percent)
                        })
                    }) // for handling the progress of the upload
                }
                return myXhr
            },     
            success: function (response) {
                currentChunkIndex++
                if (response.success || response.success === 'true') {
                    if (currentChunkIndex < totalChunks) {
                        _sendFile(currentChunkIndex)
                    } else {
                        sweetAlertToast.update({
                            html: progressBody('100', 'bg-success')
                        })
                        // All chuncks sent successfully
                        if (formData.has('file')) {
                            formData.delete('file')
                        }
                        //Add all file information
                        $.each(fileInfo, function (key, value) {
                            formData.set(key, value)
                        })
                        formData.append('eof', true)
                        $.ajax({
                            url: '{$this->url}',
                            data: formData,
                            type: 'POST',
                            dataType: 'json',
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if (response.success || response.success === 'true') {
                                    sweetAlertToast.close()
                                    if (response.result.file_type === 'images') {
                                        insertFile(response.result.path, response.result.name, response.result.path)
                                    } else if (response.result.file_type === 'videos') {
                                        insertFile(response.result.path, response.result.poster)
                                    }
                                }
                            }
                        })
                    }
                } else {
                    sweetAlertToast.update({
                        toast: true,
                        position: 'top-end',
                        html: '',
                        title: response.result,
                        icon: 'error',
                    })
                }
                // endof all chunks uploads
            }
        })
    }
    _sendFile(currentChunkIndex)
}
UPLOAD_FILE_TO_LOCAL;

        $additinal[] = /** @lang JavaScript */ <<< UPLOAD_FILE_TO_QINIU
function uploadFilesToQiniu(file, insertFile) {
    const fileInfo = getFileInfo(file)
    let customVars = {$this->_encodedMetaData}
    customVars['x:file_type'] = fileInfo.fileType
    const putExtra = {
        fname: fileInfo.name,
        mimeType: fileInfo.mimeType,
        customVars: customVars,
    }
    const config = {
        useCdnDomain: true,
        debugLogLevel: true,
    }

    const observable = qiniu.upload(file, fileInfo.key, '{$this->getQiniuToken()}', putExtra, config)
    const observer = {
        next(res) {
            sweetAlertToast.update({
                html: progressBody(res.total.percent.toFixed(2))
            })
        }, 
        error(err) {
            sweetAlertToast.update({
                toast: true,
                position: 'top-end',
                html: '',
                title: err.data.error,
                icon: 'error'
            })
        }, 
        complete(res) {
            sweetAlertToast.close()
            if (res.success) {
                insertFile(response.result.path, response.result.name, response.result.path)
            }
        }
    }
    const subscription = observable.subscribe(observer)
    sweetAlertToast.fire({
        allowEscapeKey: false,
    })
}
UPLOAD_FILE_TO_QINIU;

        $additinalJs = implode("\n", $additinal);
        $view->registerJs($additinalJs);
    }

    private function registerPlugins($view)
    {
        if ($this->enablePluginUploadAttachment) {
            EditorPluginUploadAttachmentAsset::register($view);
        }
        if ($this->enablePluginUploadAttachment) {
            EditorPluginImageModalAsset::register($view);
        }
        if ($this->enablePluginUploadAttachment) {
            EditorPluginLinkCardAsset::register($view);
        }
    }
}

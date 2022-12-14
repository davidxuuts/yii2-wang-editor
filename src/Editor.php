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
use davidxu\base\assets\QiniuJsAsset;
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

        $this->registerAssets($_view);

//        $this->_encodedMetaData = Json::encode($this->metaData);
        $customUploadJs = /** @lang JavaScript */ <<< CUSTOM_UPLOAD_JS
({
    async customUpload(file, insertFile) {
        if ({$this->secondUpload} || {$this->secondUpload} === 'true') {
            editorSecondUploadFile(file, insertFile).then(res => {
                if (!res) {
                    editorHandleUploadDrive(file, insertFile)
                }
            })
        } else {
            editorHandleUploadDrive(file, insertFile)
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
            'class' => 'editor???wrapper',
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

        // config editor
        if ($this->enablePluginUploadAttachment) {
            $editor[] = new JsExpression('window.wangEditor.Boot.registerModule(window.WangEditorPluginUploadAttachment.default)');
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
const {$this->_optionsId}Editor = window.wangEditor.createEditor({
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
const {$this->_optionsId}EditorToolbar = window.wangEditor.createToolbar({
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
            $additinal[] = /** @lang JavaScript */ <<< GET_INFO_BY_HASH
function editorSecondUploadFile(file, insertFile) {
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
                    response = (typeof response) === 'string' ? JSON.parse(response) : response
                    response = (typeof response.response) === 'string' ? JSON.parse(response.response) : response.response
                    if (response) {
                        if (response.file_type === 'images') {
                            insertFile(response.path, response.name, response.path)
                        } else if (response.file_type === 'videos') {
                            insertFile(response.path, response.poster)
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

        $additinal[] = /** @lang JavaScript */ <<< HANDLE_UPLOAD_DRIVE
function editorHandleUploadDrive(file, insertFile) {
    if ({$this->isQiniuDrive()}) {
        editorUploadFilesToQiniu(file, insertFile)
    } else if ({$this->isLocalDrive()}) {
        editorUploadFilesToLocal(file, insertFile)
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

        $additinal[] = /** @lang JavaScript */ <<< UPLOAD_FILE_TO_LOCAL
function editorUploadFilesToLocal(file, insertFile) {
    sweetAlertToast.fire({
        allowEscapeKey: false,
    })
    
    const blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice
    const fileInfo = getFileInfo(file, '{$this->uploadBasePath}')
    let chunkSize = parseInt('{$this->chunkSize}')
    const totalChunks = Math.ceil(file.size / chunkSize)
    let currentChunkIndex = 0
    let formData = new FormData()
    $.each({$this->_encodedMetaData}, function (key, value) {
        formData.append(key,value)
    })
    formData.append('key', fileInfo.key)
    formData.append('size', fileInfo.size)
    formData.append('extension', fileInfo.extension)
    formData.append('chunk_key', fileInfo.chunk_key)
    formData.append('file_type', fileInfo.file_type)
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
                response = (typeof response) === 'string' ? JSON.parse(response) : response
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
                                response = (typeof response) === 'string' ? JSON.parse(response) : response
                                if (response.success || response.success === 'true') {
                                    sweetAlertToast.close()
                                    response = (typeof response.response) === 'string' ? JSON.parse(response.response) : response.response
                                    if (response.file_type === 'images') {
                                        insertFile(response.path, response.name, response.path)
                                    } else if (response.file_type === 'videos') {
                                        insertFile(response.path, response.poster)
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
                        title: response.response,
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
function editorUploadFilesToQiniu(file, insertFile) {
    const fileInfo = getFileInfo(file, '{$this->uploadBasePath}')
     const config = {
        useCdnDomain: true,
        chunkSize: Math.floor({$this->chunkSize},  1024 * 1024)
    }
    let customVars = {$this->_encodedMetaData}
    customVars['x:file_type'] = fileInfo.file_type
    const putExtra = {
        fname: fileInfo.name,
        mimeType: fileInfo.mime_type,
        customVars: customVars,
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
        complete(response) {
            sweetAlertToast.close()
            response = (typeof response) === 'string' ? JSON.parse(response) : response
            if (response.success) {
                response = (typeof response.response) === 'string' ? JSON.parse(response.response) : response.response
                insertFile(response.path, response.name, response.path)
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

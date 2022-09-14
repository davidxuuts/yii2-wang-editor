<?php

namespace davidxu\weditor;

use davidxu\base\enums\QiniuUploadRegionEnum;
use davidxu\base\enums\UploadTypeEnum;
use davidxu\base\helpers\StringHelper;
use davidxu\config\helpers\ArrayHelper;
use davidxu\weditor\assets\QiniuJsAsset;
use davidxu\weditor\assets\SummernoteAsset;
use Qiniu\Auth;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;
use davidxu\base\widgets\InputWidget;
use Yii;

/**
 * Editor Class
 * @property array $codeMirrorOptions
 */
class Editor extends InputWidget
{
    /** @var array */
    public $clientOptions = [
    
    ];
    private $_encodedClientOptions;
    private $_encodedMetaData;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if (!isset($this->clientOptions['lang']) && Yii::$app->language !== 'en-US') {
            $this->clientOptions['lang'] = Yii::$app->language;
//            $this->clientOptions['lang'] = substr(Yii::$app->language, 0, 2);
        }

        $this->options['class'] = 'form-control';
        if (!$this->hasModel()) {
            $this->options['id'] = $this->getFieldId();
        }
        parent::init();
        $_view = $this->getView();
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
        $this->configureClientOptions();
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->registerScripts();
        echo $this->hasModel()
            ? Html::activeTextarea($this->model, $this->attribute, $this->options)
            : Html::textarea($this->name, $this->value, $this->options);
    }

    private function registerAssets($view)
    {
        EditorAsset::register($view);
        if ((bool)$this->isQiniuDrive()) {
            QiniuJsAsset::register($view);
        }
    }

    private function getFieldId()
    {
        return $this->hasModel() ? Html::getInputId($this->model, $this->attribute) : StringHelper::getInputId($this->name);
    }

    private function registerScripts()
    {
        $js = <<<JS
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

JS;
        $_view = $this->getView();
        $_view->registerJs($js);
    }

    private function configureClientOptions()
    {
        $this->_encodedClientOptions = Json::encode(ArrayHelper::merge($this->clientOptions, [
        ]));
    }
}

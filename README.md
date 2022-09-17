A WangEditor 5 for Yii2 framework
=============
A WYSIWYG Rich text [wangEditor 5](https://www.wangeditor.com/) extension for [Yii2](https://www.yiiframework.com/)

一个所见即所得富文本编辑器[wangEditor 5](https://www.wangeditor.com/)的[Yii2](https://www.yiiframework.com/)扩展。


Functions 功能
------------
It supports multiple wangEditor5 instances on one page.

本扩展支持同一个页面多个wangEditor实例。

It also supports second upload function by used Qiniu [QETag](https://github.com/qiniu/qetag).

本扩展通过使用七牛[QETag](https://github.com/qiniu/qetag)支持秒传功能。

Chunk upload is also enabled. If custom `chunkSize` more than system size (`get_cfg_var('upload_max_filesize')`), system upload max filesize will be used

本扩展已设置为使用分片上传。如果设置的`chunkSize`大于系统设置值 (`get_cfg_var('upload_max_filesize')`), 将会使用系统值。

You can upload files to local server or [Qiniu Kodo](https://developer.qiniu.com/kodo) currently.

可以上传文件到本地服务器或者直接上传文件到七牛对象存储 Kodo。

For more Qiniu Kodo policy please refer to Qiniu website.

要了解七牛对象存储Kodo的更多信息和相关安全策略，请自行参考七牛官方网站。

We use custom function for video/image upload and insert.

本扩展采用自定义功能上传和插入视频/图片。

Installation 安装
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

安装本扩展最便捷的方法是通过Composer安装。

Either run 执行

```
php composer.phar require --prefer-dist davidxu/yii2-wang-editor
```

or add 或增加

```
"davidxu/yii2-wang-editor": "*"
```

to the require section of your `composer.json` file.

到`composer.json`相关区域。


Usage 使用
-----
If you want to store files information in DB (MySQL/MariaDB), please excute migration file by

如果你想保存文件信息到数据库(MySQL/MariaDB)，请首先执行以下迁移文件
```
yii migrate/up @davidxu/base/migrations
```
and then simply use it in your code by:

然后先这样使用:

### for Local upload 上传到本地服务器

------

##### In View 视图端
```php
<?php
use davidxu\weditor\Editor;
use yii\helpers\Url;

// without ActiveForm
echo Editor::widget([
    'model' => $model,
    'attribute' => 'image_src',
    'name' => 'image_src', // If no model and attribute pointed
    'url' => Url::to('@web/upload/local'),
    'clientOptions' => [
        // 'foo' => 'bar',
    ],
    'secondUpload' => true, // default false
    'getHashUrl' => Url::to('@web/upload/get-hash'), // required for secondUpload is true
    'uploadBasePath' => 'uploads/',
    'storeInDB' => true, // return file id in DB to image url instead of file url if true, migrate model db first. default true. If second upload is true, must be true
    'metaData' => ['foo' => 'bar',],
    'chunkSize' => '2M', // if more than `get_cfg_var('upload_max_filesize')`, will use system upload max filesize
]); ?>

<?php
// with ActiveForm
echo $form->field($model, 'image_src')
    ->widget(Dropzone::class, [
        'url' => Url::to('@web/upload/local'),
        // ....
]);?>

```

##### In Upload Controller 控制器端
```php
use davidxu\base\actions\LocalAction;
use davidxu\base\models\Attachment;
use yii\web\Controller;

class UploadController extends Controller
{
    public function actions(): array
    {
        $actions = parent::actions();
        return ArrayHelper::merge([
            'local' => [
                'class' => LocalAction::class,
                'url' => Yii::getAlias('@web/uploads'), // default: '@web/uploads'. stored file base url,
                'fileDir' => Yii::getAlias('@webroot/uploads'), // default: '@webroot/uploads'. file store in this dirctory,
                'allowAnony' => true, // default false
                'attachmentModel' => Attachment::class,  // Or other extended ModelClass
            ],
        ], $actions);
    }
}
```

### for Qiniu upload 直传到七牛存储服务Kodo

------

##### In View 视图端
```php
<?php
use davidxu\weditor\Editor;
use davidxu\base\enums\QiniuUploadRegionEnum;
use davidxu\base\enums\UploadTypeEnum;
use yii\helpers\Url;

echo Editor::widget([
    'model' => $model,
    'attribute' => 'image_src',
    'name' => 'image_src', // If no model and attribute pointed
    'url' => QiniuUploadRegionEnum::getValue(QiniuUploadRegionEnum::EC_ZHEJIANG_2),
    'drive' => UploadTypeEnum::DRIVE_QINIU,
    'clientOptions' => [
        // 'foo' => 'bar',
    ],
    // ...... (refer to local config in view)
]); ?>

<?php
// with ActiveForm
echo $form->field($model, 'image_src')
    ->widget(Dropzone::class, [
    'url' => QiniuUploadRegionEnum::getValue(QiniuUploadRegionEnum::EC_ZHEJIANG_2),
    'drive' => UploadTypeEnum::DRIVE_QINIU,
    'qiniuBucket' => Yii::$app->params['qiniu.bucket'],
    'qiniuAccessKey' => Yii::$app->params['qiniu.bucket'],
    'qiniuSecretKey' => Yii::$app->params['qiniu.bucket'],
    'qiniuCallbackUrl' => Yii::$app->params['qiniu.bucket'],
    // default 'qiniuCallbackBody' here, you can modify them.
//    'qiniuCallbackBody' => [
//        'drive' => UploadTypeEnum::DRIVE_QINIU,
//        'specific_type' => '$(mimeType)',
//        'file_type' => '$(x:file_type)',
//        'path' => '$(key)',
//        'hash' => '$(etag)',
//        'size' => '$(fsize)',
//        'name' => '$(fname)',
//        'extension' => '$(ext)',
//        'member_id' => '$(x:member_id)',
//        'width' => '$(imageInfo.width)',
//        'height' => '$(imageInfo.height)',
//        'duration' => '$(avinfo.format.duration)',
//        'store_in_db' => '$(x:store_in_db)',
//        'upload_ip' => '$(x:upload_ip)',
//    ];
    // ...... (refer to local config in view)
]);?>

```

##### In Upload Controller 控制器端
```php
use davidxu\base\actions\QiniuAction;
use davidxu\base\models\Attachment;
use yii\web\Controller;
use yii\web\BadRequestHttpException;

class UploadController extends Controller
{
     /**
     * @throws BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        $currentAction = $action->id;
        $novalidateActions = ['qiniu'];
        if(in_array($currentAction, $novalidateActions)) {
            // disable CSRF validation
            $action->controller->enableCsrfValidation = false;
        }
        parent::beforeAction($action);
        return true;
    }
    public function actions(): array
    {
        $actions = parent::actions();
        return ArrayHelper::merge([
            'qiniu' => [
                'class' => QiniuAction::class,
                'url' => Yii::getAlias('@web/uploads'), // default: '@web/uploads'. stored file base url,
                'allowAnony' => true, // default false
                'attachmentModel' => Attachment::class,
            ],
        ], $actions);
    }
}
```

If Second Upload enabled, 

如果启用秒传功能，

##### In View 视图端
```php
<?= Editor::widget([
    'secondUpload' => true,
    'getHashUrl' => Url::to('@web/upload/get-hash'), // required for secondUpload is true
    // ...... (other config in view)
]); 
?>
```

##### In Upload Controller 控制器端
```php
use davidxu\base\actions\GetHashAction;
use davidxu\base\models\Attachment;
use yii\web\Controller;
use yii\helpers\ArrayHelper;

class UploadController extends Controller
{
    public function actions(): array
    {
        $actions = parent::actions();
        return ArrayHelper::merge([
            'get-hash' => [
                'class' => GetHashAction::class,
                'attachmentModel' => Attachment::class,
            ],
        ], $actions);
    }
}
```

Have fun!

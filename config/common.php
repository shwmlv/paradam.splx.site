<?php
	// paradam.me.loc/config/common.php
	
	use yii\helpers\ArrayHelper;
	
	$params = ArrayHelper::merge(
		require(__DIR__ . '/params.php'),
		require(__DIR__ . '/params-local.php')
	);
	
	return [
		'name' => 'Paradam.me',
		'basePath' => dirname(__DIR__),
		'bootstrap' => ['log'],
		'modules' => [
			'main' => [
				'class' => 'app\modules\main\Module',
			],
			'user' => [
				'class' => 'app\modules\user\Module',
			],
			'services' => [
				'class' => 'app\modules\services\Module',
			],
		],
		'aliases' => [
			'@bower' => '@vendor/bower-asset',
			'@npm' => '@vendor/npm-asset',
			'@tests' => '@app/tests',
		],
		'components' => [
			'db' => [
				'class' => 'yii\db\Connection',
				'charset' => 'utf8',
			],
			'i18n' => [
				'translations' => [
					'app' => [
						'class' => 'yii\i18n\PhpMessageSource',
						'forceTranslation' => true,
					],
				],
			],
			'urlManager' => [
				'class' => 'yii\web\UrlManager',
				'enablePrettyUrl' => true,
				'showScriptName' => false,
				'rules' => [
					'' => 'main/default/index',
					'contact' => 'main/contact/index',
					'<_a:(about|error)>' => 'main/default/<_a>',
					'<_a:(login|logout)>' => 'user/default/<_a>',
					
					'<_m:[\w\-]+>' => '<_m>/default/index',
					'<_m:[\w\-]+>/<id:\d+>' => '<_m>/default/view',
					'<_m:[\w\-]+>/<id:\d+>/<_a:[\w-]+>' => '<_m>/default/<_a>',
					'<_m:[\w\-]+>/<_c:[\w\-]+>' => '<_m>/<_c>/index',
					'<_m:[\w\-]+>/<_c:[\w\-]+>/<id:\d+>' => '<_m>/<_c>/view',
					'<_m:[\w\-]+>/<_c:[\w\-]+>/<id:\d+>/<_a:[\w\-]+>' => '<_m>/<_c>/<_a>',
					'<_m:[\w\-]+>/<_c:[\w\-]+>/<_a:[\w-]+>' => '<_m>/<_c>/<_a>',
				],
			],
			'mailer' => [
				'class' => 'yii\swiftmailer\Mailer',
			],
			'cache' => [
				'class' => 'yii\caching\FileCache',
			],
			'log' => [
				'class' => 'yii\log\Dispatcher',
			],
		],
		'params' => $params,
	];
<?php

// Default (Template) Project/${FILE_NAME}
	
	
	use yii\bootstrap\ActiveForm;
	use yii\captcha\Captcha;
	use yii\helpers\Html;
	
	$this->title = 'PhoneSignup';
	$this->params['breadcrumbs'][] = $this->title;
?>
    <div class="user-default-signup">
        <h1><?= Html::encode($this->title) ?></h1>

        <p>Please fill out the following fields to signup:</p>

        <div class="row">
            <div class="col-lg-5">
				<?php $form = ActiveForm::begin(['id' => 'form-signup']); ?>
				
				<?= $form->field($model, 'first_name')->textInput(['autofocus' => true]) ?>

				<?= $form->field($model, 'last_name')->textInput(['autofocus' => true]) ?>

	            <?= $form->field($model, 'email') ?>

				<?= $form->field($model, 'country')->textInput(['autofocus' => true]) ?>

				<?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>

				<?= $form->field($model, 'birthday')->textInput(['autofocus' => true]) ?>

				<?= $form->field($model, 'telephone') ?>
				
				<?= $form->field($model, 'password')->passwordInput() ?>

                <div class="form-group">
					<?= Html::submitButton('Signup', ['class' => 'btn btn-primary', 'name' => 'signup-button']) ?>
                </div>
				
				<?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
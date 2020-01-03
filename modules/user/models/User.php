<?php

namespace app\modules\user\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $username
 * @property string|null $auth_key
 * @property string|null $email_confirm_token
 * @property string $password_hash
 * @property string|null $password_reset_token
 * @property string $email
 * @property int $status
 */
class User extends \yii\db\ActiveRecord implements IdentityInterface
{
	const SCENARIO_PROFILE = 'profile';
	
	const STATUS_BLOCKED = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_WAIT = 2;
	
	
	public function behaviors()
	{
		return [
			TimestampBehavior::className(),
		];
	}
	
	/**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
	    return [
		    ['username', 'required'],
		    ['username', 'match', 'pattern' => '#^[\w_-]+$#is'],
		    ['username', 'unique', 'targetClass' => self::className(), 'message' => Yii::t('app', 'ERROR_USERNAME_EXISTS')],
		    ['username', 'string', 'min' => 2, 'max' => 255],
		
		    ['email', 'required', 'except' => self::SCENARIO_PROFILE],
		    ['email', 'email', 'except' => self::SCENARIO_PROFILE],
		    ['email', 'unique', 'targetClass' => self::className(), 'except' => self::SCENARIO_PROFILE, 'message' => Yii::t('app', 'ERROR_EMAIL_EXISTS')],
		    ['email', 'string', 'max' => 255, 'except' => self::SCENARIO_PROFILE],
		
		    ['status', 'integer'],
		    ['status', 'default', 'value' => self::STATUS_ACTIVE],
		    ['status', 'in', 'range' => array_keys(self::getStatusesArray())],
	    ];
    }
	
	public function scenarios()
	{
		return ArrayHelper::merge(parent::scenarios(), [
		self::SCENARIO_PROFILE => ['email'],
	]);
	}

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
	    return [
		    'id' => 'ID',
		    'created_at' => 'Создан',
		    'updated_at' => 'Обновлён',
		    'username' => 'Имя пользователя',
		    'email' => 'Email',
		    'status' => 'Статус',
	    ];
    }
	public function getStatusName()
	{
		return ArrayHelper::getValue(self::getStatusesArray(), $this->status);
	}
	
	public static function getStatusesArray()
	{
		return [
			self::STATUS_BLOCKED => 'Заблокирован',
			self::STATUS_ACTIVE => 'Активен',
			self::STATUS_WAIT => 'Ожидает подтверждения',
		];
	}
	
	/**
	 * Finds an identity by the given ID.
	 * @param string|int $id the ID to be looked for
	 * @return IdentityInterface|null the identity object that matches the given ID.
	 * Null should be returned if such an identity cannot be found
	 * or the identity is not in an active state (disabled, deleted, etc.)
	 */
	public static function findIdentity($id)
	{
		return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
	}
	
	/**
	 * Finds an identity by the given token.
	 * @param mixed $token the token to be looked for
	 * @param mixed $type the type of the token. The value of this parameter depends on the implementation.
	 * For example, [[\yii\filters\auth\HttpBearerAuth]] will set this parameter to be `yii\filters\auth\HttpBearerAuth`.
	 * @return void the identity object that matches the given token.
	 * Null should be returned if such an identity cannot be found
	 * or the identity is not in an active state (disabled, deleted, etc.)
	 * @throws NotSupportedException
	 */
	public static function findIdentityByAccessToken($token, $type = null)
	{
		throw new NotSupportedException('findIdentityByAccessToken is not implemented.');
	}
	
	/**
	 * Returns an ID that can uniquely identify a user identity.
	 * @return string|int an ID that uniquely identifies a user identity.
	 */
	public function getId()
	{
		return $this->getPrimaryKey();
	}
	
	/**
	 * Returns a key that can be used to check the validity of a given identity ID.
	 *
	 * The key should be unique for each individual user, and should be persistent
	 * so that it can be used to check the validity of the user identity.
	 *
	 * The space of such keys should be big enough to defeat potential identity attacks.
	 *
	 * This is required if [[User::enableAutoLogin]] is enabled. The returned key will be stored on the
	 * client side as a cookie and will be used to authenticate user even if PHP session has been expired.
	 *
	 * Make sure to invalidate earlier issued authKeys when you implement force user logout, password change and
	 * other scenarios, that require forceful access revocation for old sessions.
	 *
	 * @return string a key that is used to check the validity of a given identity ID.
	 * @see validateAuthKey()
	 */
	public function getAuthKey()
	
	{
		return $this->auth_key;
	}
	
	/**
	 * Validates the given auth key.
	 *
	 * This is required if [[User::enableAutoLogin]] is enabled.
	 * @param string $authKey the given auth key
	 * @return bool whether the given auth key is valid.
	 * @see getAuthKey()
	 */
	public function validateAuthKey($authKey)
	{
		return $this->getAuthKey() === $authKey;
	}
	/**
	 * Finds user by username
	 *
	 * @param string $username
	 * @return static|null
	 */
	public static function findByUsername($username)
	{
		return static::findOne(['username' => $username]);
	}
	
	/**
	 * Validates password
	 *
	 * @param string $password password to validate
	 * @return boolean if password provided is valid for current user
	 */
	public function validatePassword($password)
	{
		return Yii::$app->security->validatePassword($password, $this->password_hash);
	}
	
	/**
	 * @param string $password
	 * @throws \yii\base\Exception
	 */
	public function setPassword($password)
	{
		$this->password_hash = Yii::$app->security->generatePasswordHash($password);
	}
	
	/**
	 * Generates "remember me" authentication key
	 * @throws \yii\base\Exception
	 */
	public function generateAuthKey()
	{
		$this->auth_key = Yii::$app->security->generateRandomString();
	}
	
	public function beforeSave($insert)
	{
		if (parent::beforeSave($insert)) {
			if ($insert) {
				$this->generateAuthKey();
			}
			return true;
		}
		return false;
	}
	/**
	 * Finds user by password reset token
	 *
	 * @param string $token password reset token
	 * @return static|null
	 */
	public static function findByPasswordResetToken($token)
	{
		if (!static::isPasswordResetTokenValid($token)) {
			return null;
		}
		return static::findOne([
			'password_reset_token' => $token,
			'status' => self::STATUS_ACTIVE,
		]);
	}
	
	/**
	 * Finds out if password reset token is valid
	 *
	 * @param string $token password reset token
	 * @return boolean
	 */
	public static function isPasswordResetTokenValid($token)
	{
		if (empty($token)) {
			return false;
		}
		$expire = Yii::$app->params['user.passwordResetTokenExpire'];
		$parts = explode('_', $token);
		$timestamp = (int) end($parts);
		return $timestamp + $expire >= time();
	}
	
	/**
	 * Generates new password reset token
	 * @throws \yii\base\Exception
	 */
	public function generatePasswordResetToken()
	{
		$this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
	}
	
	/**
	 * Removes password reset token
	 */
	public function removePasswordResetToken()
	{
		$this->password_reset_token = null;
	}
	/**
	 * @param string $email_confirm_token
	 * @return static|null
	 */
	public static function findByEmailConfirmToken($email_confirm_token)
	{
		return static::findOne(['email_confirm_token' => $email_confirm_token, 'status' => self::STATUS_WAIT]);
	}
	
	/**
	 * Generates email confirmation token
	 * @throws \yii\base\Exception
	 */
	public function generateEmailConfirmToken()
	{
		$this->email_confirm_token = Yii::$app->security->generateRandomString();
	}
	
	/**
	 * Removes email confirmation token
	 */
	public function removeEmailConfirmToken()
	{
		$this->email_confirm_token = null;
	}
}
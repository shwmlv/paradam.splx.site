<?php

namespace app\modules\user\models;

use app\modules\user\models\query\UserQuery;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property int $created_at
 * @property int $updated_at
 * @property string $username
 * @property float $balance
 * @property float $sms_cost
 * @property string $first_name
 * @property string $last_name
 * @property string|null $auth_key
 * @property string|null $email_confirm_token
 * @property string $password_hash
 * @property string|null $password_reset_token
 * @property string $email
 * @property string $telephone
 * @property mixed $statusName
 * @property string $password
 * @property string $authKey
 * @property int $status
 * @property string $alt
 * @property string $avatarSmall
 * @property string $avatarMedium
 * @property string $avatarBig
 * @property string $formatBalance
 * @property string $formatSmsCost
 */
class User extends ActiveRecord implements IdentityInterface
{
	public const SCENARIO_PROFILE = 'profile';
	
	public const STATUS_BLOCKED = 0;
	public const STATUS_ACTIVE = 1;
	public const STATUS_WAIT = 2;
	public const STATUS_TEST = 3;

	public const SIZE_AVATAR_SMALL = 64;
	public const SIZE_AVATAR_MEDIUM = 150;
	public const SIZE_AVATAR_BIG = 250;

	public const CURRENCY_BIT = 'bit';

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
			
//			['email', 'required', 'except' => self::SCENARIO_PROFILE],
			['email', 'email', 'except' => self::SCENARIO_PROFILE],
			['email', 'unique', 'targetClass' => self::className(), 'except' => self::SCENARIO_PROFILE, 'message' => Yii::t('app', 'ERROR_EMAIL_EXISTS')],
			['email', 'string', 'max' => 255, 'except' => self::SCENARIO_PROFILE],

//			['telephone', 'required'],
//			['telephone', 'match', 'pattern' => '/^\+380\d{3}\d{2}\d{2}\d{2}$/'],
//			['telephone', 'unique', 'targetClass' => self::className(), 'message' => Yii::t('app', 'ERROR_USERNAME_EXISTS')],
//
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
	 * @return string
	 */
	public function getFirstName() {
		return $this->first_name;
	}

	/**
	 * @return string
	 */
	public function getLastName()
	{
		return $this->last_name;
	}

	/**
	 * @return string
	 */
	public function getAvatarSmall()
	{
		return Yii::$app->request->hostInfo . '/images/user/avatar/' . $this->username . '-' . self::SIZE_AVATAR_SMALL . '.png';
	}

	/**
	 * @return string
	 */
	public function getAvatarMedium()
	{
		return Yii::$app->request->hostInfo . '/images/user/avatar/' . $this->username . '-' . self::SIZE_AVATAR_MEDIUM . '.png';
	}

	/**
	 * @return string
	 */
	public function getAvatarBig()
	{
		return Yii::$app->request->hostInfo . '/images/user/avatar/' . $this->username . '-' . self::SIZE_AVATAR_BIG . '.png';
	}

	/**
	 * @return string
	 */
	public function getAlt()
	{
		return $this->last_name ? ($this->first_name . ' ' . $this->last_name) : $this->username;
	}

	/**
	 * @return string
	 */
	public function getFormatBalance()
	{
		return number_format($this->balance, 2, '.', '') . ' ' . self::CURRENCY_BIT;
	}

	/**
	 * @return string
	 */
	public function getFormatSmsCost()
	{
		return number_format($this->sms_cost, 2, '.', '') . ' ' . self::CURRENCY_BIT;
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
	 * @return UserQuery
	 * @throws InvalidConfigException
	 */
	public static function find()
	{
		return Yii::createObject(UserQuery::className(), [get_called_class()]);
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
	
	public static function findByPhone($phone)
	{
		return static::findOne(['telephone' => $phone]);
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
	 * @param string $telephone
	 * @throws \yii\base\Exception
	 */
	public function setTelephone($telephone)
	{
		$this->telephone = $telephone;
	}

	/**
	 * @param string $first_name
	 * @throws \yii\base\Exception
	 */
	public function setName($first_name)
	{
		$this->first_name = $first_name;
	}

	/**
	 * @param string $username
	 * @throws \yii\base\Exception
	 */
	public function setUserName($username)
	{
		$this->username = $username;
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
	public static function findByPasswordResetToken($token, $timeout)
	{
		if (!static::isPasswordResetTokenValid($token, $timeout)) {
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
	public static function isPasswordResetTokenValid($token, $timeout)
	{
		if (empty($token)) {
			return false;
		}
		$parts = explode('_', $token);
		$timestamp = (int) end($parts);
		return $timestamp + $timeout >= time();
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

	public static function onLanguageChanged($event)
	{
		// $event->language: new language
		// $event->oldLanguage: old language

		// Save the current language to user record
		Yii::$app->language = $event->language;
	}
}

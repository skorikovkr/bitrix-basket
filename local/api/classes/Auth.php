<?php


namespace Legacy\API;

use Bitrix\Main;
use Legacy\Main\CLUser;

class Auth
{
    protected static $MODE_REGISTER = 1;
    protected static $MODE_FORGOT = 2;
    protected static $MODE_ASSIGN_NEW = 3;

    public static function login($arRequest)
    {
        $phone = $arRequest['phone'];
        $password = $arRequest['password'];
        if (empty($phone)) {
            throw new \Exception('Номер телефона не может быть пустым.');
        }

        $phoneNumber = Main\UserPhoneAuthTable::normalizePhoneNumber($phone);

        $USER = new CLUser;
        $arAuthResult = $USER->Login($phoneNumber, $password);
        if (is_array($arAuthResult) && $arAuthResult['TYPE'] == 'ERROR') {
            $message = is_array($arAuthResult['MESSAGE']) ? implode('. ', $arAuthResult['MESSAGE']) : $arAuthResult['MESSAGE'];
            throw new \Exception($message);
        }
    }

    public static function sendCodeForgotPassword($arRequest)
    {
        $_SESSION['LEGACY_GET_CODE_MODE'] = self::$MODE_FORGOT;

        self::sendCode($arRequest);
    }

    public static function sendCodeAssignNew($arRequest)
    {
        $_SESSION['LEGACY_GET_CODE_MODE'] = self::$MODE_ASSIGN_NEW;

        self::sendCode($arRequest);
    }

    public static function sendCode($arRequest)
    {
        if (empty($_SESSION['LEGACY_GET_CODE_MODE'])) {
            throw new \Exception('Неизвестная ошибка');
        }

        $phone = $arRequest['phone'];
        if (empty($phone)) {
            throw new \Exception('Номер телефона не может быть пустым.');
        }

        $phoneNumber = Main\UserPhoneAuthTable::normalizePhoneNumber($phone);
        $userPhone = Main\UserPhoneAuthTable::getList(["filter" => ["=PHONE_NUMBER" => $phoneNumber]])->fetchObject();

        if(!$userPhone)
        {
            throw new \Exception('Номер телефона не найден.');
        }

        if($userPhone->getDateSent())
        {
            $currentDateTime = new Main\Type\DateTime();
            if(($currentDateTime->getTimestamp() - $userPhone->getDateSent()->getTimestamp()) < CLUser::PHONE_CODE_RESEND_INTERVAL)
            {
                throw new \Exception('Новый код можно получить не чаще одного раза в минуту.');
            }
        }

        list($code, $phoneNumber) = CLUser::GeneratePhoneCode($userPhone->getUserId());

        if ($_SESSION['LEGACY_GET_CODE_MODE'] == self::$MODE_FORGOT) {
            $smsTemplate = 'SMS_USER_RESTORE_PASSWORD';
        } else {
            $smsTemplate = 'SMS_USER_CONFIRM_NUMBER';
        }

        $sms = new Main\Sms\Event(
            $smsTemplate,
            [
                "USER_PHONE" => $phoneNumber,
                "CODE" => $code,
            ]
        );
        $result = $sms->send(true);

        if(!$result->isSuccess())
        {
            throw new \Exception('Не удалось отправить смс с кодом.');
        }
    }

    public static function checkCode($arRequest)
    {
        if (empty($_SESSION['LEGACY_GET_CODE_MODE'])) {
            throw new \Exception('Неизвестная ошибка');
        }

        $USER = new CLUser;
        $phone = Main\UserPhoneAuthTable::normalizePhoneNumber($arRequest['phone']);
        $code = $arRequest['code'];

        $userId = intval($USER::VerifyPhoneCode($phone, $code));
        if ($userId > 0) {
            $fields = ['ACTIVE' => 'Y'];
            if ($_SESSION['LEGACY_GET_CODE_MODE'] == self::$MODE_ASSIGN_NEW) {
                $fields['LOGIN'] = $phone;
            }
            $_SESSION['LEGACY_GET_CODE_MODE'] = null;
            $USER->Update($userId, $fields);
            if ($USER->LAST_ERROR) {
                throw new \Exception($USER->LAST_ERROR);
            }
            $_SESSION['LEGACY_AUTH_CONFIRMED'] = true;
            return true;
        }

        throw new \Exception('Неверный код проверки');
    }

    public static function registration($arRequest)
    {
        $_SESSION['LEGACY_GET_CODE_MODE'] = self::$MODE_REGISTER;
        $USER = new CLUser;

        $phone = $arRequest['phone'];
        $name = $arRequest['name'];
        $password = $arRequest['password'];
        $passwordConfirm = $arRequest['passwordConfirm'];

        if ($password != $passwordConfirm) {
            throw new \Exception('Пароли не совпадают.');
        }

        $phoneNumber = Main\UserPhoneAuthTable::normalizePhoneNumber($phone);

        $userPhone = Main\UserPhoneAuthTable::getList(["filter" => ["=PHONE_NUMBER" => $phoneNumber]])->fetchObject();
        if ($userPhone) {
            if (!$userPhone->getConfirmed()) {
                self::sendCode($arRequest);
            } else {
                throw new \Exception('Пользователь с таким номером телефона уже зарегистрирован.');
            }
        } else {
            $arRegisterResult = $USER->Register($phoneNumber, $name, '', $password, $passwordConfirm, null, false, '', 0, false, $phoneNumber);
            if (is_array($arRegisterResult) && $arRegisterResult['TYPE'] == 'ERROR') {
                $message = is_array($arRegisterResult['MESSAGE']) ? implode('. ', $arRegisterResult['MESSAGE']) : $arRegisterResult['MESSAGE'];
                throw new \Exception($message);
            }
        }
    }

    public static function changePassword($arRequest)
    {
        $USER = new CLUser;

        $phone = $arRequest['phone'];
        $password = $arRequest['password'];

        if (empty($phone)) {
            throw new \Exception('Номер телефона не может быть пустым.');
        }

        $phoneNumber = Main\UserPhoneAuthTable::normalizePhoneNumber($phone);

        $fields = [
            'PASSWORD' => $password
        ];

        $rsUser = $USER::GetByLogin($phoneNumber);
        if ($arUser = $rsUser->Fetch()) {
            if ($arUser['ACTIVE'] == 'Y' && $_SESSION['LEGACY_AUTH_CONFIRMED']) {
                $user = new CLUser;
                $user->Update($arUser['ID'], $fields);
                if ($user->LAST_ERROR) {
                    throw new \Exception($user->LAST_ERROR);
                }
                $_SESSION['LEGACY_AUTH_CONFIRMED'] = false;
            }
        }
    }

    public function logout() {
        global $USER;
        $USER->Logout();
    }
}
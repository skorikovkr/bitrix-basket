<?php

namespace Legacy\API;

use Bitrix\Main\UserTable;
use Exception;

class User 
{
    public static function get()
    {
        global $USER;
        $result = null;
        if ($USER->IsAuthorized()) {
            $arrUser = \CUser::GetByID($USER->GetId())->GetNext();
            $result = [
                'id' => $arrUser['ID'],
                'name' => $arrUser['NAME'],
                'email' => $arrUser['EMAIL'],
                'phone' => $arrUser['LOGIN'],
                'is_receive_messages' => (bool) ($arrUser['UF_RECEIVE_MESSAGES'] ?? false),
            ];
        }
        return $result;
    }

    public static function update($arRequest)
    {
        global $USER;
        if ($userData = self::get()) {
            $fields = [];

            if (!empty($arRequest['name']) && $userData['name'] != $arRequest['name']) {
                $fields['NAME'] = $arRequest['name'];
            }

            if (!empty($arRequest['email']) && $userData['email'] != $arRequest['email']) {
                $email = filter_var($arRequest['email'], FILTER_VALIDATE_EMAIL);
                if ($email === false) {
                   throw new \Exception('Не верный формат email.');
                }
                $fields['EMAIL'] = $email;
                $fields['UF_RECEIVE_MESSAGES'] = $arRequest['is_receive_messages'];
            }

            $phoneChanged = false;
            if (!empty($arRequest['phone']) && $userData['phone'] != $arRequest['phone']) {
                if (self::checkPassword($arRequest)) {
                    $fields['PHONE_NUMBER'] == $arRequest['phone'];
                    $phoneChanged = true;
                }
            }

            if ($USER->Update($userData['id'], $fields)) {
                if ($phoneChanged) {
                    Auth::sendCodeAssignNew($arRequest);
                }
                return self::get();
            } else {
                throw new \Exception($USER->LAST_ERROR);
            }
        }

        throw new Exception('Произошла неизвестная ошибка.');
    }

    public static function changePassword($arRequest)
    {
        global $USER;
        if ($userData = self::get()) {
            if (!empty($arRequest['password'])) {
                if (self::checkPassword($arRequest)) {
                    if (!empty($arRequest['new_password']) && !empty($arRequest['new_password_confirmation'])) {
                        if ($arRequest['new_password'] != $arRequest['new_password_confirmation']) {
                            throw new \Exception('Пароли не совпадают.');
                        }

                        $fields = [
                            'PASSWORD' => $arRequest['new_password'],
                            'CONFIRM_PASSWORD' => $arRequest['new_password_confirmation']
                        ];
                        if (!$USER->Update($userData['id'], $fields)) {
                            throw new \Exception($USER->LAST_ERROR);
                        }

                        return true;
                    }
                }
            }
        }

        throw new \Exception('Неизвестная ошибка');
    }

    public static function checkPassword($arRequest)
    {
        global $USER;
        if ($USER->IsAuthorized()) {
            $userData = UserTable::getRow([
                'select' => [
                    'PASSWORD',
                ],
                'filter' => [
                    'ID' => $USER->GetID()
                ],
            ]);
            $result = \Bitrix\Main\Security\Password::equals($userData['PASSWORD'], $arRequest['password']);
            if ($result) {
                return true;
            } else {
                throw new \Exception('Проверка пароля не пройдена.');
            }
        }
    }
}
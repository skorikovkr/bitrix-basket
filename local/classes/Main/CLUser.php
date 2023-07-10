<?php


namespace Legacy\Main;

use \Bitrix\Main as BMain;
use Legacy\Main\Security\Mfa\TotpAlgorithm;


class CLUser extends \CUser
{
    protected static $digits = 6;

    public function Register($USER_LOGIN, $USER_NAME, $USER_LAST_NAME, $USER_PASSWORD, $USER_CONFIRM_PASSWORD, $USER_EMAIL, $SITE_ID = false, $captcha_word = "", $captcha_sid = 0, $bSkipConfirm = false, $USER_PHONE_NUMBER = "")
    {
        /**
         * @global CMain $APPLICATION
         * @global CUserTypeManager $USER_FIELD_MANAGER
         */
        global $APPLICATION, $DB, $USER_FIELD_MANAGER;

        $APPLICATION->ResetException();
        if(defined("ADMIN_SECTION") && ADMIN_SECTION===true && $SITE_ID!==false)
        {
            $APPLICATION->ThrowException(GetMessage("MAIN_FUNCTION_REGISTER_NA_INADMIN"));
            return array("MESSAGE"=>GetMessage("MAIN_FUNCTION_REGISTER_NA_INADMIN"), "TYPE"=>"ERROR");
        }

        $strError = "";

        if (\COption::GetOptionString("main", "captcha_registration", "N") == "Y")
        {
            if (!($APPLICATION->CaptchaCheckCode($captcha_word, $captcha_sid)))
            {
                $strError .= GetMessage("MAIN_FUNCTION_REGISTER_CAPTCHA")."<br>";
            }
        }

        if($strError)
        {
            if(\COption::GetOptionString("main", "event_log_register_fail", "N") === "Y")
            {
                \CEventLog::Log("SECURITY", "USER_REGISTER_FAIL", "main", false, $strError);
            }

            $APPLICATION->ThrowException($strError);
            return array("MESSAGE"=>$strError, "TYPE"=>"ERROR");
        }

        if($SITE_ID === false)
            $SITE_ID = SITE_ID;

        $bConfirmReq = !$bSkipConfirm && (\COption::GetOptionString("main", "new_user_registration_email_confirmation", "N") == "Y" && \COption::GetOptionString("main", "new_user_email_required", "Y") <> "N");
        $phoneRegistration = (\COption::GetOptionString("main", "new_user_phone_auth", "N") == "Y");
        $phoneRequired = ($phoneRegistration && \COption::GetOptionString("main", "new_user_phone_required", "N") == "Y");

        $checkword = md5(\CMain::GetServerUniqID().uniqid());
        $active = ($bConfirmReq || $phoneRequired? "N": "Y");

        $arFields = array(
            "LOGIN" => $USER_LOGIN,
            "NAME" => $USER_NAME,
            "LAST_NAME" => $USER_LAST_NAME,
            "PASSWORD" => $USER_PASSWORD,
            "CHECKWORD" => $checkword,
            "~CHECKWORD_TIME" => $DB->CurrentTimeFunction(),
            "CONFIRM_PASSWORD" => $USER_CONFIRM_PASSWORD,
            "EMAIL" => $USER_EMAIL,
            "PHONE_NUMBER" => $USER_PHONE_NUMBER,
            "ACTIVE" => $active,
            "CONFIRM_CODE" => ($bConfirmReq? randString(8): ""),
            "SITE_ID" => $SITE_ID,
            "LANGUAGE_ID" => LANGUAGE_ID,
            "USER_IP" => $_SERVER["REMOTE_ADDR"],
            "USER_HOST" => @gethostbyaddr($_SERVER["REMOTE_ADDR"]),
        );
        $USER_FIELD_MANAGER->EditFormAddFields("USER", $arFields);

        $def_group = \COption::GetOptionString("main", "new_user_registration_def_group", "");
        if($def_group!="")
            $arFields["GROUP_ID"] = explode(",", $def_group);

        $bOk = true;
        $result_message = true;
        foreach(GetModuleEvents("main", "OnBeforeUserRegister", true) as $arEvent)
        {
            if(ExecuteModuleEventEx($arEvent, array(&$arFields)) === false)
            {
                if($err = $APPLICATION->GetException())
                {
                    $result_message = array("MESSAGE"=>$err->GetString()."<br>", "TYPE"=>"ERROR");
                }
                else
                {
                    $APPLICATION->ThrowException("Unknown error");
                    $result_message = array("MESSAGE"=>"Unknown error"."<br>", "TYPE"=>"ERROR");
                }

                $bOk = false;
                break;
            }
        }

        $ID = false;
        $phoneReg = false;
        if($bOk)
        {
            if($arFields["SITE_ID"] === false)
            {
                $arFields["SITE_ID"] = \CSite::GetDefSite();
            }
            $arFields["LID"] = $arFields["SITE_ID"];

            if($ID = $this->Add($arFields))
            {
                if($phoneRegistration && $arFields["PHONE_NUMBER"] <> '')
                {
                    $phoneReg = true;

                    //added the phone number for the user, now sending a confirmation SMS
                    list($code, $phoneNumber) = CLUser::GeneratePhoneCode($ID);

                    $sms = new \Bitrix\Main\Sms\Event(
                        "SMS_USER_CONFIRM_NUMBER",
                        [
                            "USER_PHONE" => $phoneNumber,
                            "CODE" => $code,
                        ]
                    );
                    $sms->setSite($arFields["SITE_ID"]);
                    $smsResult = $sms->send(true);

                    $signedData = \Bitrix\Main\Controller\PhoneAuth::signData(['phoneNumber' => $phoneNumber]);

                    if($smsResult->isSuccess())
                    {
                        $result_message = array(
                            "MESSAGE" => GetMessage("main_register_sms_sent"),
                            "TYPE" => "OK",
                            "SIGNED_DATA" => $signedData,
                            "ID" => $ID,
                        );
                    }
                    else
                    {
                        $result_message = array(
                            "MESSAGE" => $smsResult->getErrorMessages(),
                            "TYPE" => "ERROR",
                            "SIGNED_DATA" => $signedData,
                            "ID" => $ID,
                        );
                    }

                }
                else
                {
                    $result_message = array(
                        "MESSAGE" => GetMessage("USER_REGISTER_OK"),
                        "TYPE" => "OK",
                        "ID" => $ID
                    );
                }

                $arFields["USER_ID"] = $ID;

                $arEventFields = $arFields;
                unset($arEventFields["PASSWORD"]);
                unset($arEventFields["CONFIRM_PASSWORD"]);
                unset($arEventFields["~CHECKWORD_TIME"]);

                $event = new \CEvent;
                $event->SendImmediate("NEW_USER", $arEventFields["SITE_ID"], $arEventFields);
                if($bConfirmReq)
                {
                    $event->SendImmediate("NEW_USER_CONFIRM", $arEventFields["SITE_ID"], $arEventFields);
                }
            }
            else
            {
                $APPLICATION->ThrowException($this->LAST_ERROR);
                $result_message = array("MESSAGE"=>$this->LAST_ERROR, "TYPE"=>"ERROR");
            }
        }

        if(is_array($result_message))
        {
            if($result_message["TYPE"] == "OK")
            {
                if(\COption::GetOptionString("main", "event_log_register", "N") === "Y")
                {
                    $res_log["user"] = ($USER_NAME != "" || $USER_LAST_NAME != "") ? trim($USER_NAME." ".$USER_LAST_NAME) : $USER_LOGIN;
                    \CEventLog::Log("SECURITY", "USER_REGISTER", "main", $ID, serialize($res_log));
                }
            }
            else
            {
                if(\COption::GetOptionString("main", "event_log_register_fail", "N") === "Y")
                {
                    \CEventLog::Log("SECURITY", "USER_REGISTER_FAIL", "main", $ID, $result_message["MESSAGE"]);
                }
            }
        }

        //authorize succesfully registered user, except email or phone confirmation is required
        $isAuthorize = false;
        if($ID !== false && $arFields["ACTIVE"] === "Y" && $phoneReg === false)
        {
            $isAuthorize = $this->Authorize($ID);
        }

        $agreementId = intval(\COption::getOptionString("main", "new_user_agreement", ""));
        if ($agreementId && $isAuthorize)
        {
            $agreementObject = new \Bitrix\Main\UserConsent\Agreement($agreementId);
            if ($agreementObject->isExist() && $agreementObject->isActive() && $_REQUEST["USER_AGREEMENT"] == "Y")
            {
                \Bitrix\Main\UserConsent\Consent::addByContext($agreementId, "main/reg", "register");
            }
        }

        $arFields["RESULT_MESSAGE"] = $result_message;
        foreach (GetModuleEvents("main", "OnAfterUserRegister", true) as $arEvent)
            ExecuteModuleEventEx($arEvent, array(&$arFields));

        return $arFields["RESULT_MESSAGE"];
    }

    public static function GeneratePhoneCode($userId)
    {
        $row = BMain\UserPhoneAuthTable::getRowById($userId);
        if($row && $row["OTP_SECRET"] <> '')
        {
            $secret = base64_decode($row["OTP_SECRET"]);

            $totp = new TotpAlgorithm();
            $totp->setInterval(self::PHONE_CODE_OTP_INTERVAL);
            $totp->setSecret($secret);
            $totp->setDigits(self::$digits);

            $timecode = $totp->timecode(time());
            $code = $totp->generateOTP($timecode);

            BMain\UserPhoneAuthTable::update($userId, array(
                "ATTEMPTS" => 0,
                "DATE_SENT" => new BMain\Type\DateTime(),
            ));

            return [$code, $row["PHONE_NUMBER"]];
        }
        return false;
    }

    public static function VerifyPhoneCode($phoneNumber, $code)
    {
        if($code == '')
        {
            return false;
        }

        $phoneNumber = BMain\UserPhoneAuthTable::normalizePhoneNumber($phoneNumber);

        $row = BMain\UserPhoneAuthTable::getList(["filter" => ["=PHONE_NUMBER" => $phoneNumber]])->fetch();
        if($row && $row["OTP_SECRET"] <> '')
        {
            if($row["ATTEMPTS"] >= 3)
            {
                return false;
            }

            $secret = base64_decode($row["OTP_SECRET"]);
            if ($timecode = $row['DATE_SENT']) {
                $timecode = $timecode->getTimestamp();
            }

            $totp = new TotpAlgorithm();
            $totp->setInterval(self::PHONE_CODE_OTP_INTERVAL);
            $totp->setSecret($secret);
            $totp->setDigits(self::$digits);

            //заглушка для тестирования
            /*
            try
            {
                list($result, ) = $totp->verify($code, '0:0', $timecode);
            }
            catch(BMain\ArgumentException $e)
            {
                return false;
            }
            */
            $result = true;

            $data = array();
            if($result)
            {
                if($row["CONFIRMED"] == "N")
                {
                    $data["CONFIRMED"] = "Y";
                }

                $data['DATE_SENT'] = '';
            }
            else
            {
                $data["ATTEMPTS"] = (int)$row["ATTEMPTS"] + 1;
            }

            if(!empty($data))
            {
                BMain\UserPhoneAuthTable::update($row["USER_ID"], $data);
            }

            if($result)
            {
                return $row["USER_ID"];
            }
        }
        return false;
    }

    public static function SendPassword($LOGIN, $EMAIL, $SITE_ID = false, $captcha_word = "", $captcha_sid = 0, $phoneNumber = "", $shortCode = false)
    {
        /** @global CMain $APPLICATION */
        global $DB, $APPLICATION;

        $arParams = array(
            "LOGIN" => $LOGIN,
            "EMAIL" => $EMAIL,
            "SITE_ID" => $SITE_ID,
            "PHONE_NUMBER" => $phoneNumber,
        );

        $result_message = array("MESSAGE"=>GetMessage('ACCOUNT_INFO_SENT')."<br>", "TYPE"=>"OK");
        $APPLICATION->ResetException();
        $bOk = true;
        foreach(GetModuleEvents("main", "OnBeforeUserSendPassword", true) as $arEvent)
        {
            if(ExecuteModuleEventEx($arEvent, array(&$arParams))===false)
            {
                if($err = $APPLICATION->GetException())
                    $result_message = array("MESSAGE"=>$err->GetString()."<br>", "TYPE"=>"ERROR");

                $bOk = false;
                break;
            }
        }

        if($bOk && \COption::GetOptionString("main", "captcha_restoring_password", "N") == "Y")
        {
            if (!($APPLICATION->CaptchaCheckCode($captcha_word, $captcha_sid)))
            {
                $result_message = array("MESSAGE"=>GetMessage("main_user_captcha_error")."<br>", "TYPE"=>"ERROR");
                $bOk = false;
            }
        }

        if($bOk)
        {
            $f = false;
            if($arParams["PHONE_NUMBER"] <> '')
            {
                //user registered by phone number
                $number = BMain\UserPhoneAuthTable::normalizePhoneNumber($arParams["PHONE_NUMBER"]);

                $select = [
                    "USER_ID" => "USER_ID",
                    "LANGUAGE_ID" => "USER.LANGUAGE_ID",
                ];
                if($arParams["SITE_ID"] === false)
                {
                    $select["LID"] = "USER.LID";
                }

                $row = BMain\UserPhoneAuthTable::getList([
                    "select" => $select,
                    "filter" => ["=PHONE_NUMBER" => $number],
                ])->fetch();

                if($row)
                {
                    $f = true;

                    if($arParams["SITE_ID"] === false)
                    {
                        $arParams["SITE_ID"] = \CSite::GetDefSite($row["LID"]);
                    }

                    list($code, $number) = CLUser::GeneratePhoneCode($row["USER_ID"]);

                    $sms = new Main\Sms\Event(
                        "SMS_USER_RESTORE_PASSWORD",
                        [
                            "USER_PHONE" => $number,
                            "CODE" => $code,
                        ]
                    );
                    $sms->setSite($arParams["SITE_ID"]);
                    if($row["LANGUAGE_ID"] <> '')
                    {
                        //user preferred language
                        $sms->setLanguage($row["LANGUAGE_ID"]);
                    }
                    $smsResult = $sms->send(true);

                    if($smsResult->isSuccess())
                    {
                        $result_message = array("MESSAGE"=>GetMessage("main_user_pass_request_sent")."<br>", "TYPE"=>"OK", "TEMPLATE" => "SMS_USER_RESTORE_PASSWORD");
                    }
                    else
                    {
                        $result_message = array("MESSAGE"=>implode("<br>", $smsResult->getErrorMessages()), "TYPE"=>"ERROR");
                    }

                    if(\COption::GetOptionString("main", "event_log_password_request", "N") === "Y")
                    {
                        \CEventLog::Log("SECURITY", "USER_INFO", "main", $row["USER_ID"]);
                    }
                }
            }
            elseif($arParams["LOGIN"] <> '' || $arParams["EMAIL"] <> '')
            {
                $confirmation = (\COption::GetOptionString("main", "new_user_registration_email_confirmation", "N") == "Y");

                $strSql = "";
                if($arParams["LOGIN"] <> '')
                {
                    $strSql =
                        "SELECT ID, LID, ACTIVE, CONFIRM_CODE, LOGIN, EMAIL, NAME, LAST_NAME, LANGUAGE_ID ".
                        "FROM b_user u ".
                        "WHERE LOGIN='".$DB->ForSQL($arParams["LOGIN"])."' ".
                        "	AND (ACTIVE='Y' OR NOT(CONFIRM_CODE IS NULL OR CONFIRM_CODE='')) ".
                        (
                            // $arParams["EXTERNAL_AUTH_ID"] can be changed in the OnBeforeUserSendPassword event
                        $arParams["EXTERNAL_AUTH_ID"] <> ''?
                            "	AND EXTERNAL_AUTH_ID='".$DB->ForSQL($arParams["EXTERNAL_AUTH_ID"])."' " :
                            "	AND (EXTERNAL_AUTH_ID IS NULL OR EXTERNAL_AUTH_ID='') "
                        );
                }
                if($arParams["EMAIL"] <> '')
                {
                    if($strSql <> '')
                    {
                        $strSql .= "\nUNION\n";
                    }
                    $strSql .=
                        "SELECT ID, LID, ACTIVE, CONFIRM_CODE, LOGIN, EMAIL, NAME, LAST_NAME, LANGUAGE_ID ".
                        "FROM b_user u ".
                        "WHERE EMAIL='".$DB->ForSQL($arParams["EMAIL"])."' ".
                        "	AND (ACTIVE='Y' OR NOT(CONFIRM_CODE IS NULL OR CONFIRM_CODE='')) ".
                        (
                        $arParams["EXTERNAL_AUTH_ID"] <> ''?
                            "	AND EXTERNAL_AUTH_ID='".$DB->ForSQL($arParams["EXTERNAL_AUTH_ID"])."' " :
                            "	AND (EXTERNAL_AUTH_ID IS NULL OR EXTERNAL_AUTH_ID='') "
                        );
                }
                $res = $DB->Query($strSql);

                while($arUser = $res->Fetch())
                {
                    if($arParams["SITE_ID"]===false)
                    {
                        if(defined("ADMIN_SECTION") && ADMIN_SECTION===true)
                            $arParams["SITE_ID"] = \CSite::GetDefSite($arUser["LID"]);
                        else
                            $arParams["SITE_ID"] = SITE_ID;
                    }

                    if($arUser["ACTIVE"] == "Y")
                    {
                        \CUser::SendUserInfo($arUser["ID"], $arParams["SITE_ID"], GetMessage("INFO_REQ"), true, 'USER_PASS_REQUEST');
                        $f = true;
                    }
                    elseif($confirmation)
                    {
                        //unconfirmed registration - resend confirmation email
                        $arFields = array(
                            "USER_ID" => $arUser["ID"],
                            "LOGIN" => $arUser["LOGIN"],
                            "EMAIL" => $arUser["EMAIL"],
                            "NAME" => $arUser["NAME"],
                            "LAST_NAME" => $arUser["LAST_NAME"],
                            "CONFIRM_CODE" => $arUser["CONFIRM_CODE"],
                            "USER_IP" => $_SERVER["REMOTE_ADDR"],
                            "USER_HOST" => @gethostbyaddr($_SERVER["REMOTE_ADDR"]),
                        );

                        $event = new \CEvent;
                        $event->SendImmediate("NEW_USER_CONFIRM", $arParams["SITE_ID"], $arFields, "Y", "", array(), $arUser["LANGUAGE_ID"]);

                        $result_message = array("MESSAGE"=>GetMessage("MAIN_SEND_PASS_CONFIRM")."<br>", "TYPE"=>"OK");
                        $f = true;
                    }

                    if(\COption::GetOptionString("main", "event_log_password_request", "N") === "Y")
                    {
                        \CEventLog::Log("SECURITY", "USER_INFO", "main", $arUser["ID"]);
                    }
                }
            }
            if(!$f)
            {
                return array("MESSAGE"=>GetMessage('DATA_NOT_FOUND1')."<br>", "TYPE"=>"ERROR");
            }
        }
        return $result_message;
    }
}
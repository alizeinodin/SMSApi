<?php

namespace Alizne\SmsApi;

use Exception;

class SMSApi
{
    private string $URL_SMS = "https://RestfulSms.com/api";
    private string $LineNumber;
    private mixed $Token;
    private string $APIUrl = "https://RestfulSms.com/api/Token";
    private string $SecretKey;
    private string $APIKey;

    /**
     * this constructor initial value's
     * api key and secret key should get from sms.ir
     * line number also is must be initial
     * this value's comes from env file
     *
     * @example for env file:
     *
     * SMSAPI_API_KEY="fkajsklflasfosadfj"
     * SMSAPI_SECRET_KEY="saflw2h3i41284"
     * SMSAPI_LINE_NUMBER="30008"
     *
     */
    public function __construct()
    {
        $this->APIKey = config('SMSApi.api_key');
        $this->SecretKey = config('SMSApi.secret_key');
        $this->Token = $this->getToken();
        $this->LineNumber = config('SMSApi.line_number');
    }

    /**
     * for usage sms.ir api you must have token for every request
     * so this method making api for send request
     *
     * @return mixed
     * @throws Exception
     */
    public function getToken(): mixed
    {

        $postData = [
            "UserApiKey" => $this->APIKey,
            "SecretKey" => $this->SecretKey,
        ];

        $postString = json_encode($postData);
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->APIUrl,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postString,

        ]);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            print_r(curl_error($ch));
        }
        curl_close($ch);
        $result = json_decode($result);
        if (is_object($result)) {
            if ($result->IsSuccessful) {
                $this->Token = $result->TokenKey;
                return $result->TokenKey;
            } else throw new Exception($result->Message);
        }
        throw new Exception("Api wasn't make!");
    }

    /**
     * @throws Exception
     */
    private function CURL(
        $url,
        $postData = NULL /* Default Value for get requests */,
        $customRequest = NULL): bool|string
    {
        /*
         * custom request is other request like put, push
         * if it was set, will use and if it was null.
         * method true is post and false is get
         */
        $customRequest ?: ($postData ? $method = true : $method = false);

        $postData = json_encode($postData);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json',
                "x-sms-ir-secure-token: {$this->Token}"],
            CURLOPT_POST => $method,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        /*
         * type of request. put , push, post, get, if it set custom, will use
         * and if was not set, it is post or get
         */
        $customRequest ?
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $customRequest) :
            curl_setopt($ch, CURLOPT_POST, $method);

        // set opt is for post method. so if it is set, postField will set
        !$method ?: curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

    /**
     * this method sending verify sms to user
     * unlike sending sms, this method is very fast to send
     * and can send for any mobile number also mobile number's which blocked advertising SMS's
     *
     * @param $code
     * @param $phoneNumber
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function SendVerifySMS($code, $phoneNumber, $URL = "/VerificationCode"): bool|string
    {
        $postData = [
            'Code' => $code,
            'MobileNumber' => $phoneNumber,
        ];
        return $this->CURL($this->URL_SMS . $URL, $postData);
    }


    /**
     * send sms to mobile numbers
     *
     * @param array $phoneNumbers
     * @param $text
     * @param null $SendDateTime
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function SendingSMS(array $phoneNumbers, $text, $SendDateTime = NULL, $URL = "/MessageSend"): bool|string
    {
        $postData = [
            "Messages" => array($text),
            "MobileNumbers" => $phoneNumbers,
            "LineNumber" => $this->LineNumber,
            "SendDateTime" => $SendDateTime ? $SendDateTime : now()->toDateString(),
            "CanContinueInCaseOfError" => false
        ];
        return $this->CURL($this->URL_SMS . $URL, $postData);
    }

    /**
     * getting balance charge of your line number
     *
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function getBalanceCharge(string $URL = '/credit'): bool|string
    {
        return $this->CURL($this->URL_SMS . $URL);
    }

    /**
     * to get your line number's, you can use
     * this method
     *
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function getSMSLines(string $URL = '/SMSLine'): bool|string
    {
        return $this->CURL($this->URL_SMS . $URL);
    }

    /**
     * you can change your line number
     * by this method
     *
     * @param $lineNumber
     */
    public function changeLineNumber($lineNumber)
    {
        $this->LineNumber = $lineNumber;
    }

    /**
     * @param $id
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function incomingMessageListWithId($id, string $URL = '/ReceiveMessage'): bool|string
    {
        $getURL = $this->URL_SMS . $URL . "?id={$id}";
        return $this->CURL($getURL);
    }

    /**
     * @param $dateFrom
     * @param $dateTo
     * @param $rowsPerPage
     * @param $page
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function incomingMessageListWithDate($dateFrom, $dateTo, $rowsPerPage, $page, $URL = '/ReceiveMessage'): bool|string
    {
        $getURL = $this->URL_SMS . $URL . "
                ?Shamsi_FromDate={$dateFrom}
                &Shamsi_ToDate={$dateTo}
                &RowsPerPage={$rowsPerPage}
                &RequestedPageNumber={$page}";

        return $this->CURL($getURL);
    }

    /**
     * @param $id
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function incomingMessageWithId($id, string $URL = "/ReceiveMessageWithId"): bool|string
    {
        $getURL = $this->URL_SMS . $URL . "?id={$id}";
        return $this->CURL($getURL);
    }

    /**
     * @param $dataOfUser
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function addUserInClubContact($dataOfUser, string $URL = '/CustomerClubContact'): bool|string
    {
        $postData = [
            'Prefix' => $dataOfUser['prefix'],
            'FirstName' => $dataOfUser['FirstName'],
            'LastName' => $dataOfUser['LastName'],
            'Mobile' => $dataOfUser['Mobile'],
            'BirthDay' => $dataOfUser['BirthDay'],
            'CategoryId' => $dataOfUser['CategoryID']
        ];
        return $this->CURL($this->URL_SMS . $URL, $postData);
    }

    /**
     * @param $dataOfUser
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function updateUserInClubContact($dataOfUser, string $URL = '/CustomerClubContact'): bool|string
    {
        // data is update for user where entered mobile number
        $postData = [
            'Prefix' => $dataOfUser['prefix'],
            'FirstName' => $dataOfUser['FirstName'],
            'LastName' => $dataOfUser['LastName'],
            'Mobile' => $dataOfUser['Mobile'],
            'BirthDay' => $dataOfUser['BirthDay'],
            'CategoryId' => $dataOfUser['CategoryID']
        ];
        return $this->CURL($this->URL_SMS . $URL, $postData, "PUT");
    }

    /**
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function getCustomerClubContactCategories(string $URL = '/CustomerClubContact/GetCategories'): bool|string
    {
        return $this->CURL($URL);
    }

    /**
     * @param $page
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function getCustomerClubContactList($page, string $URL = '/CustomerClubContact/GetContacts'): bool|string
    {
        // pagination of this function: 10 records in per page

        $getURL = $this->URL_SMS . $URL . "?pageNumber={$page}";
        return $this->CURL($getURL);
    }

    /**
     * @param $mobile
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function deleteUserFromCustomerClubContact($mobile, string $URL = '/CustomerClub/DeleteContactCustomerClub'): bool|string
    {
        $postData = [
            'Mobile' => $mobile,
            "CanContinueInCaseOfError" => false
        ];

        return $this->CURL($this->URL_SMS . $URL, $postData);
    }

    /**
     * @param $messages
     * @param $mobileNumbers
     * @param string $date
     * @param bool $CanContinueInCaseOfError
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function sendSMSToClubContact($messages, $mobileNumbers,
                                         string $date = "",
                                         bool $CanContinueInCaseOfError = true,
                                         string $URL = '/CustomerClub/Send'): bool|string
    {
        $postData = [
            'Messages' => $messages,
            'MobileNumbers' => $mobileNumbers,
            'SendDateTime' => $date,
            'CanContinueInCaseOfError' => $CanContinueInCaseOfError,
        ];
        return $this->CURL($this->URL_SMS . $URL, $postData);
    }

    /**
     * @param $dataOfUser
     * @param $message
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function addContactAndSendSMSInClub($dataOfUser, $message, string $URL = '/CustomerClub/AddContactAndSend'): bool|string
    {
        $postData = [
            'Prefix' => $dataOfUser['prefix'],
            'FirstName' => $dataOfUser['FirstName'],
            'LastName' => $dataOfUser['LastName'],
            'Mobile' => $dataOfUser['Mobile'],
            'BirthDay' => $dataOfUser['BirthDay'],
            'CategoryId' => $dataOfUser['CategoryID'],
            'MessageText' => $message
        ];

        return $this->CURL($this->URL_SMS . $URL, $postData);

    }

    /**
     * @param $message
     * @param $categoryIds
     * @param string $date
     * @param bool $CanContinueInCaseOfError
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function sendSMSToGroupOfClub($message, $categoryIds,
                                         string $date = "",
                                         bool $CanContinueInCaseOfError = true,
                                         string $URL = '/CustomerClub/SendToCategories'): bool|string
    {
        $postData = [
            "Messages" => $message,
            "contactsCustomerClubCategoryIds" => $categoryIds,
            "SendDateTime" => $date,
            "CanContinueInCaseOfError" => $CanContinueInCaseOfError,
        ];

        return $this->CURL($this->URL_SMS . $URL, $postData);
    }

    /**
     * @param int $pageIndex
     * @param int $rowCount
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function getMessageReportClubContact(int    $pageIndex = 1,
                                                int    $rowCount = 10,
                                                string $URL = '/CustomerClub/GetSendMessagesByPagination'): bool|string
    {
        // pageIndex    => number of index of page
        // rowCount     => number of record in per page

        $getURL = $this->URL_SMS . $URL . "?pageIndex={$pageIndex}&rowCount={$rowCount}";
        return $this->CURL($getURL);
    }

    /**
     * @param int $lastID
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function getMessageReportClubContactWithId(int    $lastID = 1,
                                                      string $URL = '/CustomerClub/GetSendMessagesByPaginationAndLastId'): bool|string
    {
        /*
         * this method can return 50 records at last
         * if you want other record's you should use next last id
         */

        $getURL = $this->URL_SMS . $URL . "?lastId={$lastID}";
        return $this->CURL($getURL);
    }

    /**
     * @param $data
     * @param $mobile
     * @param $templateId
     *
     * @return bool|string
     * @throws Exception
     */
    public function ultraFastSendMessageWithTemplateAndToken($data, $mobile, $templateId): bool|string
    {
        /*
         * structure of data:
         * key most be parameter in template of SMS.ir and value is parameter value
         */
        $URL = '/UltraFastSend';
        $parameterArray = [];

        foreach ($data as $key => $value) {
            array_merge($parameterArray, ['Parameter' => $key, 'ParameterValue' => $value]);
        }

        $postData = [
            "ParameterArray" => $parameterArray,
            "Mobile" => $mobile,
            "TemplateId" => $templateId,
        ];

        return $this->CURL($this->URL_SMS . $URL, $postData);

    }

    /**
     * @param $reportType
     * @param $sentReturnId
     * @param $fromDate
     * @param $toDate
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function reportOfUltraFastSendMessage($reportType, $sentReturnId, $fromDate, $toDate,
                                                 string $URL = '/MessageReport'): bool|string
    {
        $postData = [
            "ReportType" => $reportType,
            "SentReturnId" => $sentReturnId,
            "FromDate" => $fromDate,
            "ToDate" => $toDate,
        ];

        return $this->CURL($this->URL_SMS . $URL, $postData);
    }

    /**
     * @param $verificationCodeId
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function getReportOfUltraFastSendMessage($verificationCodeId, string $URL = '/UltraFastSend/'): bool|string
    {
        $getURL = $this->URL_SMS . $URL . "/{$verificationCodeId}";
        return $this->CURL($getURL);
    }

    /**
     * @throws Exception
     */
    public function addTemplateUltraSendMessage($templateText, $URL = '/FastSendTemplate'): bool|string
    {
        /*
         * template text must be a structure like this:
         * "کد تایید شما : [Code]"
         * Code is Parameter and ParameterValue can replace it to send SMS
         */

        $postData = $templateText;

        return $this->CURL($postData);
    }

    /**
     * @param $templateId
     * @param string $URL
     *
     * @return bool|string
     * @throws Exception
     */
    public function statusOfTemplateOfUltraSendMessage($templateId, string $URL = '/FastSendTemplate'): bool|string
    {
        $postURL = $this->URL_SMS . $URL . "/{$templateId}";
        return $this->CURL($postURL, NULL, "POST");
    }
}

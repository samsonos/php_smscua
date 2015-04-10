<?php
/**
 * Created by PhpStorm.
 * User: zyablitskiy
 * Date: 10.04.2015
 * Time: 15:54
 */
namespace samson\smscua;

use samson\core\iModuleCompressable;

/* Main class for sending messages via smsc.ua service */
class eSputnik extends \samson\core\Service implements iModuleCompressable
{
    protected $id = 'smscua';

    public $module = 'smscua';
    /* Login for account on smsc.ua service */
    public $login;
    /* Password or MD5-hashed password for account on smsc.ua service */
    public $password;
    /* Email to fill field from */
    public $smscFrom = 'api@smsc.ua';
    /* Use method POST */
    public $smscPOST = 0;
    /* Use HTTPS protocol */
    public $smscHTTPS = 0;
    /* Set encoding */
    public $smscCharset = 'utf-8';
    /* Debug flag */
    public $smscDebug = 0;

    public function beforeCompress(& $obj = null, array & $code = null)
    {

    }
    public function afterCompress( & $obj = null, array & $code = null )
    {

    }
    /**
     *  Функция отправки SMS
     *
     * обязательные параметры:
     * @param $phones - список телефонов через запятую или точку с запятой
     * @param $message - отправляемое сообщение
     *
     * необязательные параметры:
     * @param int $translit  - переводить или нет в транслит (1,2 или 0)
     * @param int $time - необходимое время доставки в виде строки (DDMMYYhhmm, h1-h2, 0ts, +m)
     * @param int $id - идентификатор сообщения. Представляет собой 32-битное число в диапазоне от 1 до 2147483647.
     * @param int $format - формат сообщения
     * (0 - обычное sms, 1 - flash-sms, 2 - wap-push, 3 - hlr, 4 - bin, 5 - bin-hex, 6 - ping-sms, 7 - mms, 8 - mail, 9 - call)
     * @param bool $sender - имя отправителя (Sender ID).
     * Для отключения Sender ID по умолчанию необходимо в качестве имени передать пустую строку или точку.
     * @param string $query - строка дополнительных параметров, добавляемая в URL-запрос ("valid=01:00&maxsms=3&tz=2")
     * @param array $files - массив путей к файлам для отправки mms или e-mail сообщений
     * @return mixed возвращает массив (<id>, <количество sms>, <стоимость>, <баланс>) в случае успешной отправки
     *               либо массив (<id>, -<код ошибки>) в случае ошибки
     */

    public function send_sms($phones, $message, $translit = 0, $time = 0, $id = 0, $format = 0, $sender = false, $query = "", $files = array())
    {
        static $formats = array(1 => "flash=1", "push=1", "hlr=1", "bin=1", "bin=2", "ping=1", "mms=1", "mail=1", "call=1");

        $m = $this->_smsc_send_cmd("send", "cost=3&phones=".urlencode($phones)."&mes=".urlencode($message).
            "&translit=$translit&id=$id".($format > 0 ? "&".$formats[$format] : "").
            ($sender === false ? "" : "&sender=".urlencode($sender)).
            ($time ? "&time=".urlencode($time) : "").($query ? "&$query" : ""), $files);

        // (id, cnt, cost, balance) или (id, -error)

        if ($this->smscDebug) {
            if ($m[1] > 0)
                echo "Сообщение отправлено успешно. ID: $m[0], всего SMS: $m[1], стоимость: $m[2], баланс: $m[3].\n";
            else
                echo "Ошибка №", -$m[1], $m[0] ? ", ID: ".$m[0] : "", "\n";
        }

        return $m;
    }

    // SMTP версия функции отправки SMS
    public function send_sms_mail($phones, $message, $translit = 0, $time = 0, $id = 0, $format = 0, $sender = "")
    {
        return mail("send@send.smsc.ua", "", $this->login.":".$this->password.":$id:$time:$translit,$format,$sender:$phones:$message",
            "From: ".$this->smscFrom."\nContent-Type: text/plain; charset=".$this->smscCharset."\n");
    }

    /**
     * Функция получения стоимости SMS
     *
     * обязательные параметры:
     * @param $phones - список телефонов через запятую или точку с запятой
     * @param $message - отправляемое сообщение
     *
     * необязательные параметры:
     * @param int $translit - переводить или нет в транслит (1,2 или 0)
     * @param int $format - имя отправителя (Sender ID)
     * @param bool $sender - имя отправителя (Sender ID)
     * @param string $query - строка дополнительных параметров, добавляемая в URL-запрос
     *                      ("list=79999999999:Ваш пароль: 123\n78888888888:Ваш пароль: 456")
     * @return mixed возвращает массив (<стоимость>, <количество sms>) либо массив (0, -<код ошибки>) в случае ошибки
     */
    public function get_sms_cost($phones, $message, $translit = 0, $format = 0, $sender = false, $query = "")
    {
        static $formats = array(1 => "flash=1", "push=1", "hlr=1", "bin=1", "bin=2", "ping=1", "mms=1", "mail=1", "call=1");

        $m = $this->_smsc_send_cmd("send", "cost=1&phones=".urlencode($phones)."&mes=".urlencode($message).
            ($sender === false ? "" : "&sender=".urlencode($sender)).
            "&translit=$translit".($format > 0 ? "&".$formats[$format] : "").($query ? "&$query" : ""));

        // (cost, cnt) или (0, -error)

        if ($this->smscDebug) {
            if ($m[1] > 0)
                echo "Стоимость рассылки: $m[0]. Всего SMS: $m[1]\n";
            else
                echo "Ошибка №", -$m[1], "\n";
        }

        return $m;
    }

    /**
     * Функция проверки статуса отправленного SMS или HLR-запроса
     *
     * @param $id - ID cообщения или список ID через запятую
     * @param $phone - номер телефона или список номеров через запятую
     * @param int $all - вернуть все данные отправленного SMS, включая текст сообщения (0,1 или 2)
     * @return array возвращает массив (для множественного запроса двумерный массив):
     * для HLR-запроса:
     *(<статус>, <время изменения>, <код ошибки sms>, <код IMSI SIM-карты>, <номер сервис-центра>, <код страны регистрации>, <код оператора>,
     * <название страны регистрации>, <название оператора>, <название роуминговой страны>, <название роумингового оператора>)
     *
     * при $all = 1 дополнительно возвращаются элементы в конце массива:
     * (<время отправки>, <номер телефона>, <стоимость>, <sender id>, <название статуса>, <текст сообщения>)
     *
     * при $all = 2 дополнительно возвращаются элементы <страна>, <оператор> и <регион>
     *
     * при множественном запросе:
     * если $all = 0, то для каждого сообщения или HLR-запроса дополнительно возвращается <ID сообщения> и <номер телефона>
     *
     * если $all = 1 или $all = 2, то в ответ добавляется <ID сообщения>
     *
     * либо массив (0, -<код ошибки>) в случае ошибки
     */
    public function get_status($id, $phone, $all = 0)
    {
        $m = $this->_smsc_send_cmd("status", "phone=".urlencode($phone)."&id=".urlencode($id)."&all=".(int)$all);

        // (status, time, err, ...) или (0, -error)

        if (!strpos($id, ",")) {
            if ($this->smscDebug )
                if ($m[1] != "" && $m[1] >= 0)
                    echo "Статус SMS = $m[0]", $m[1] ? ", время изменения статуса - ".date("d.m.Y H:i:s", $m[1]) : "", "\n";
                else
                    echo "Ошибка №", -$m[1], "\n";

            if ($all && count($m) > 9 && (!isset($m[$idx = $all == 1 ? 14 : 17]) || $m[$idx] != "HLR")) // ',' в сообщении
                $m = explode(",", implode(",", $m), $all == 1 ? 9 : 12);
        }
        else {
            if (count($m) == 1 && strpos($m[0], "-") == 2)
                return explode(",", $m[0]);

            foreach ($m as $k => $v)
                $m[$k] = explode(",", $v);
        }

        return $m;
    }

    /**
     * Функция получения баланса
     *
     * @return mixed возвращает баланс в виде строки или false в случае ошибки
     */
    function get_balance()
    {
        $m = $this->_smsc_send_cmd("balance"); // (balance) или (0, -error)

        if ($this->smscDebug) {
            if (!isset($m[1]))
                echo "Сумма на счете: ", $m[0], "\n";
            else
                echo "Ошибка №", -$m[1], "\n";
        }

        return isset($m[1]) ? false : $m[0];
    }


/** ВНУТРЕННИЕ ФУНКЦИИ */

    /**
     * Функция вызова запроса. Формирует URL и делает 3 попытки чтения
     *
     * @param $cmd
     * @param string $arg
     * @param array $files
     * @return array
     */
    private function _smsc_send_cmd($cmd, $arg = "", $files = array())
    {
        $url = ($this->smscHTTPS ? "https" : "http")."://smsc.ua/sys/$cmd.php?login="
            .urlencode($this->login)."&psw=".urlencode($this->password)."&fmt=1&charset=".$this->smscCharset."&".$arg;

        $i = 0;
        do {
            if ($i) {
                sleep(2 + $i);

                if ($i == 2)
                    $url = str_replace('://smsc.ua/', '://www2.smsc.ua/', $url);
            }

            $ret = $this->_smsc_read_url($url, $files);
        }
        while ($ret == "" && ++$i < 4);

        if ($ret == "") {
            if ($this->smscDebug) {
                echo "Ошибка чтения адреса: $url\n";
            }
            $ret = ","; // фиктивный ответ
        }

        $delim = ",";

        if ($cmd == "status") {
            parse_str($arg);

            if (strpos($id, ",")) {
                $delim = "\n";
            }
        }

        return explode($delim, $ret);
    }

    /**
     * Функция чтения URL. Для работы должно быть доступно:
     * curl или fsockopen (только http) или включена опция allow_url_fopen для file_get_contents
     * @param $url
     * @param $files
     * @return mixed|string
     */
    private function _smsc_read_url($url, $files)
    {
        $ret = "";
        $post = $this->smscPOST || strlen($url) > 2000 || $files;

        if (function_exists("curl_init"))
        {
            static $c = 0; // keepalive

            if (!$c) {
                $c = curl_init();
                curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($c, CURLOPT_TIMEOUT, 60);
                curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
            }

            curl_setopt($c, CURLOPT_POST, $post);

            if ($post)
            {
                list($url, $post) = explode("?", $url, 2);

                if ($files) {
                    parse_str($post, $m);

                    foreach ($m as $k => $v)
                        $m[$k] = isset($v[0]) && $v[0] == "@" ? sprintf("\0%s", $v) : $v;

                    $post = $m;
                    foreach ($files as $i => $path)
                        if (file_exists($path))
                            $post["file".$i] = function_exists("curl_file_create") ? curl_file_create($path) : "@".$path;
                }

                curl_setopt($c, CURLOPT_POSTFIELDS, $post);
            }

            curl_setopt($c, CURLOPT_URL, $url);

            $ret = curl_exec($c);
        }
        elseif ($files) {
            if ($this->smscDebug)
                echo "Не установлен модуль curl для передачи файлов\n";
        }
        else {
            if (!$this->smscHTTPS && function_exists("fsockopen"))
            {
                $m = parse_url($url);

                if (!$fp = fsockopen($m["host"], 80, $errno, $errstr, 10))
                    $fp = fsockopen("212.24.33.196", 80, $errno, $errstr, 10);

                if ($fp) {
                    fwrite($fp, ($post ? "POST $m[path]" : "GET $m[path]?$m[query]")." HTTP/1.1\r\nHost: smsc.ua\r\nUser-Agent: PHP".($post ? "\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: ".strlen($m['query']) : "")."\r\nConnection: Close\r\n\r\n".($post ? $m['query'] : ""));

                    while (!feof($fp))
                        $ret .= fgets($fp, 1024);
                    list(, $ret) = explode("\r\n\r\n", $ret, 2);

                    fclose($fp);
                }
            }
            else
                $ret = file_get_contents($url);
        }

        return $ret;
    }
}

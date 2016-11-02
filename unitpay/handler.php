<?php
error_reporting(0);
include_once 'lib/ConfigWritter.php';
include_once 'lib/Model.php';
include_once 'lib/Logger.php';


class Handler
{
    function process()
    {
        try {

            $debug = ConfigWritter::getInstance()->getParameter('DEBUG');
            if ($debug){
                Logger::getInstance()->writeString($_SERVER['QUERY_STRING'], 'URL');
            }

            if (empty($_GET)) {
                header('Content-Type: text/html; charset=utf-8');
                die ('Адрес данного скрипта нужно прописать в поле "URL скрипта обработчика" в настройках вашего проекта');
            }
            if (file_exists('install.php')) {
                throw new Exception('Удалите файл install.php для корректной работы скрипта.');
            }
            $request = $_GET;
            if (empty($request['method']) || empty($request['params']) || !is_array($request['params'])) {
                throw new Exception("Некорректный запрос");
            }

            $method = $request['method'];
            $params = $request['params'];

            $signature = $this->getSha256SignatureByMethodAndParams(
                $method,
                $params,
                ConfigWritter::getInstance()->getParameter('SECRET_KEY'));

            if ($debug){
                Logger::getInstance()->writeString($signature, 'AFTER HASH STRING');
            }

            if ($params['signature'] != $signature) {
                throw new Exception("Некорректная цифровая подпись");
            }
            if (!isset($params['unitpayId']) || !isset($params['sum']) || !isset($params['account'])) {
                throw new Exception('Отсутствуют обязательные параметры платежа');
            }

            switch ($method) {
                // Проверяем что можем оказать абоненту услугу
                case 'check':
                    $this->check($params);
                    $this->responseSuccess("Успех");
                    break;
                // Оказываем услугу абоненту
                case 'pay':
                    $message = $this->pay($params);
                    $this->responseSuccess($message);
                    break;
                // Отменяем платеж
                case 'error':
                    $message = $this->error($params);
                    $this->responseSuccess($message);
                    break;
                default:
                    $this->responseError("Некорректный метод, поддерживаются методы: check, pay и error");
            }

        } catch (Exception $e) {
            $this->responseError($e->getMessage());
        }
    }
    function check($params)
    {
        $model = Model::getInstance();
        $config = ConfigWritter::getInstance();

        if ($model->getPaymentByUnitpayId($params['unitpayId'])) {
            // Платеж уже создан в БД
            return true;
        }
        $char = $model->getChar($params['account']);
        if (!$char) {
            throw new Exception('Персонаж ' . $params['account'] . ' не найден');
        }
        $itemsCount = floor($params['sum'] / $config->getParameter('ITEM_PRICE'));
        if ($itemsCount <= 0) {
            throw new Exception('Суммы ' . $params['sum'] . ' руб. не достаточно для оплаты товара ' .
                'стоимостью ' . $config->getParameter('ITEM_PRICE') . ' руб.');
        }
        if (!empty($char->charId)) {
            $charId = $char->charId;
        } elseif (!empty($char->obj_Id)) {
            $charId = $char->obj_Id;
        } else {
            throw new Exception('Персонаж найден, однако не удается получить его ID');
        }

        if (!$model->createPayment(
            $params['unitpayId'], $charId, $params['sum'], $itemsCount)
        ) {
            throw new Exception('Не удается создать платеж в БД. Проверьте права на запись');
        }

        return true;
    }

    function pay($params)
    {
        $model = Model::getInstance();
        $config = ConfigWritter::getInstance();

        $payment = $model->getPaymentByUnitpayId($params['unitpayId']);
        if (!$payment) {
            $this->check($params);
        }

        $payment = $model->getPaymentByUnitpayId($params['unitpayId']);
        if (!$payment) {
            throw new Exception('Не удается найти платеж');
        }

        if ($payment->status == 1) {
            return 'Платеж уже проведен';
        }

        if (!$model->confirmPaymentByUnitpayId($params['unitpayId'])) {
            throw new Exception('Не удается подтвердить платеж в БД. Проверьте права на запись');
        }

        if (!$model->addItemToChar($payment->account, $config->getParameter('ITEM_ID'), $payment->itemsCount)) {
            throw new Exception('Не удалось начислить персонажу требуемые вещи');
        }

        return 'Платеж успешно выполнен';
    }

    function error($params)
    {
        $model = Model::getInstance();
        $config = ConfigWritter::getInstance();

        $payment = $model->getPaymentByUnitpayId($params['unitpayId']);
        if (!$payment)
        {
            throw new Exception('Не удается найти платеж');
        }

        if ($payment->status == 2)
        {
            throw new Exception('Платеж уже отменен');
        }

        if (!$model->cancelPaymentByUnitpayId($params['unitpayId']))
        {
            throw new Exception('Не удалось отменить платеж в БД. Проверьте права на запись');
        }

        if ($payment->status == 1)
        {
            $model->removeItemFromChar($payment->account, $config->getParameter('ITEM_ID'), $payment->itemsCount);
        }

        return 'Платеж успешно отменен';
    }

    /**
     * Формирование цифровой подписи для массива параметров
     *
     * @param array $params
     * @param string $secretKey
     * @return string
     */
    function md5sign($params, $secretKey) {
        ksort($params);
        if (isset($params['sign'])) {
            unset($params['sign']);
        }

        return md5(join(null, $params).$secretKey);
    }

    /**
     * @param $method
     * @param array $params
     * @param $secretKey
     * @return string
     */
    function getSha256SignatureByMethodAndParams($method, array $params, $secretKey)
    {
        $delimiter = '{up}';
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        $str = $method.$delimiter.join($delimiter, $params).$delimiter.$secretKey;

        $debug = ConfigWritter::getInstance()->getParameter('DEBUG');
        if ($debug){
            Logger::getInstance()->writeArray($params, 'PARAMS ARRAY');
            Logger::getInstance()->writeString($str, 'BEFORE HASH STRING');
        }

        return hash('sha256', $str);
    }

    /**
     * Ошибочный ответ партнера
     *
     * @param $message
     */
    function responseError($message) {
        $error = array(
            "jsonrpc" => "2.0",
            "error" => array(
                "code" => -32000,
                "message" => $message
            ),
            'id' => 1
        );
        echo json_encode($error); exit();
    }

    /**
     * Успешный ответ партнера
     *
     * @param $message
     */
    function responseSuccess($message) {
        $success = array(
            "jsonrpc" => "2.0",
            "result" => array(
                "message" => $message
            ),
            'id' => 1
        );
        echo json_encode($success); exit();
    }
}

$handler = new Handler();
// запускаем обработчик
$handler->process();

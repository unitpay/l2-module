<?php
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');
include_once 'lib/Model.php';
include_once 'lib/ConfigWritter.php';

class Install
{
    /** $config */

    function welcome()
    {
        $this->loadView(array(
            'title' => 'Менеджер установки платежей для Lineage2',
            'content' => '<p>Добро пожаловать в «Менеджер установки»! Данный скрипт поможет Вам быстро и без особых усилий настроить модуль моментальных платежей
<a target="_blank"  href="http://unitpay.ru">Unitpay.ru</a> на Вашем сервере.</p>
                <div class="alert alert-info"><strong>Пожалуйста, перед началом установки убедитесь в том, что:</strong>
                    <ul>
                        <li>Скрипт расположен на сервере, имеющем доступ к актуальной рабочей версии БД Lineage2.</li>
                        <li>Вы зарегистрировались и создали проект в <a target="_blank" href="http://unitpay.ru">UnitPay.ru</a>.</li>
                    </ul>

                </div>
                ',
            'buttonText' => 'Начать установку',
            'action' => '?action=step1',
            'method'    =>  'post'
        ));
    }

    /**
     * Провекра окружения, версии PHP, наличия драйвера для Mysql, возможности создания файла конфигурации
     */
    function step1()
    {
        $configFileStruct = "<?php return array(
    'ITEM_PRICE' => '30',
    'ITEM_ID' => 4037,
    'SECRET_KEY' => '12345',
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_PORT' => '',
    'ITEM_TABLE' => '',
    'DB_NAME' => 'test'
);";

        $systemTests = array();

        $version = explode('.', phpversion());
        $systemTests[] = array(
            'description' => 'Проверка версии PHP. Для нормальной работы требуется версия не ниже 5.0',
            'error' => 'К сожалению ваша версия PHP не удовлетовряет требованиям «Мастер установки»',
            'status' => $version[0] >= 5 ? 'OK' : 'ERROR'
        );
        $systemTests[] = array(
            'description' => 'Проверка наличия mysqli драйвера',
            'error' => 'К сожалению нам не удалось найти установленного mysqli расширения, проверьте настройки php.ini
            на наличие активного расширения php_mysqli',
            'status' => function_exists('mysqli_connect') ? 'OK' : 'ERROR'
        );

        @chmod('config.php', 0666);
        $systemTests[] = array(
            'description' => 'Проверка возможности записи в файл config.php',
            'error' => 'Проверьте, что файл config.php существует и обладает правами на запись
            666 (для LINUX/UNIX систем)',
            'status' => (
                file_exists('config.php') && is_writable('config.php')
            ) ? 'OK' : 'ERROR'
        );

        $systemTests[] = array(
            'description' => 'Проверка целостности библиотек «Мастера установки»',
            'error' => 'Не удалось найти файлы ConfigWritter.php и Model.php в папке lib, проверьте, что данные файлы
            существуют, а также, что соблюден регистр нижних/верхних букв в названии файлов. Если файлов нет, попробуйте
            скачать и распаковать заново «Мастер установки».',
            'status' => (
                file_exists('lib/ConfigWritter.php') && file_exists('lib/Model.php')
            ) ? 'OK' : 'ERROR'
        );

        $content = '<table class="table">';
        $hasErrors = 0;
        foreach ($systemTests as $test) {
            $content .='<tr><td>'.$test['description'].'</td><td>'.
                ($test['status'] == 'OK' ?
                    '<span class="label label-success">'.$test['status'].'</span>' :
                    '<span class="label label-important">'.$test['status'].'</span>').
                '</td></tr>';
            if ($test['status'] == 'ERROR' ) {
                $hasErrors = 1;
                $content .='<tr><td class="text-error" colspan="2">'.$test['error'].'</td></tr>';
            }
        }
        $content .= '</table>';

        if (!$hasErrors) {
            $content = '<div class="alert alert-success">
Поздравляем!  Базовые тесты успешно пройдены. Вы  можете перейти к следующему шагу.
                </div>'.$content;
        }
        $this->loadView(array(
            'title' => 'Проверка среды установки',
            'content' => $content,
            'buttonText' => $hasErrors ? 'Повторить тесты' : 'Продолжить',
            'action' => $hasErrors ? '?action=step1' : '?action=step2',
            'back' => '?action=welcome',
            'method'    =>  'post'

        ));

    }

    /**
     * Параметры соединения с БД
     */
    function step2()
    {
        $error = $content = null;
        $dbCheckSuccess = false;
        try {
            if (!file_exists('config.php')) {
                throw new Exception('Файл конфигурации не найден');
            }
            $config = file_get_contents('config.php');
            if (!$config) {
                throw new Exception('Файл конфигурации config.php найден, однако он не содержит данных');
            }

            $config = ConfigWritter::getInstance();

            $dbhost = $config->getParameter('DB_HOST');
            $dbname = $config->getParameter('DB_NAME');
            $dbpass = $config->getParameter('DB_PASS');
            $dbuser = $config->getParameter('DB_USER');
            $dbport = $config->getParameter('DB_PORT');

            if (!empty($_POST)) {
                $dbhost = $_POST['DB_HOST'];
                $dbname = $_POST['DB_NAME'];
                $dbpass = $_POST['DB_PASS'];
                $dbuser = $_POST['DB_USER'];
                $dbport = $_POST['DB_PORT'];
                if (empty($dbhost) || empty($dbname) || empty($dbuser)) {
                    throw new Exception('Не установлены конфигурационные параметры для соединения с БД');
                }

                $dbport = empty($dbport)?ini_get("mysqli.default_port"):$dbport;
                $mysqli = @new mysqli (
                    $dbhost, $dbuser, $dbpass, $dbname, $dbport
                );
                /* проверка подключения */
                if (mysqli_connect_errno()) {
                    throw new Exception('Не получается соединиться с БД, проверьте правильность введенных параметров. Ошибка: ' . mysqli_connect_error());
                }

                $createUnitpayPaymentsTable = "CREATE TABLE IF NOT EXISTS `unitpay_payments` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `unitpayId` varchar(255) NOT NULL,
  `account` varchar(255) NOT NULL,
  `sum` float NOT NULL,
  `itemsCount` int(11) NOT NULL DEFAULT '1',
  `dateCreate` datetime NOT NULL,
  `dateComplete` datetime DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8";


                if (mysqli_query($mysqli, 'select 1 from unitpay_payments') === FALSE && !$mysqli->query($createUnitpayPaymentsTable)) {
                    throw new Exception("Ошибка создания таблицы платежей unitpay_payments (".$mysqli->error.").<br>
Проверьте права пользователя ".$dbuser." на создание таблиц, либо выполните запрос в БД вручную:<br>
<pre>".$createUnitpayPaymentsTable."</pre>
                    ");
                }

                $mysqli->close();

                $dbCheckSuccess = true;
                $config->setParameter('DB_HOST', $dbhost);
                $config->setParameter('DB_NAME', $dbname);
                $config->setParameter('DB_PASS', $dbpass);
                $config->setParameter('DB_USER', $dbuser);
                $config->setParameter('DB_PORT', $dbport);

                $content = '<div class="alert alert-success">Соединение с базой прошло успешно, можно переходить к следующему шагу</div>'.$content;
                header("Location: ?action=step3");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            $content = '<div class="alert alert-error">'.$error.'</div>'.$content;
        }
        $content .= '
            <p>Укажите актуальные параметры для соединения с БД, где расположены таблицы Lineage2 (не WEB-движка, а именно самой игры).</p>
            <div><label class="required" for="DB_HOST">Имя хоста</label><input type="text" value="'.$dbhost.'" style="width:484px" required="required" name="DB_HOST" id="DB_HOST"></div>
            <div><label class="required" for="DB_NAME">Имя БД</label><input type="text" value="'.$dbname.'" style="width:484px" required="required" name="DB_NAME" id="DB_NAME"></div>
            <div><label class="required" for="DB_USER">Имя пользователя</label><input type="text" value="'.$dbuser.'" style="width:484px" required="required" name="DB_USER" id="DB_USER"></div>
            <div><label class="required" for="DB_PASS">Пароль пользователя</label><input type="password" value="" style="width:484px" name="DB_PASS" id="DB_PASS"></div>
            <div><label class="required" for="DB_PORT">Номер порта(необязательный параметр)</label><input type="text" value="'.$dbport.'" style="width:484px" name="DB_PORT" id="DB_PORT"></div>
        ';
        $this->loadView(array(
            'title' => 'Проверка соединения с БД',
            'content' => $content,
            'buttonText' => $dbCheckSuccess ? 'Продолжить' : 'Продолжить',
            'action' => $dbCheckSuccess ? '?action=step3' : '?action=step2',
            'back' => '?action=step1',
            'method'    =>  'post'
        ));
    }

    /**
     * Проверка версии сервера
     */
    function step3()
    {
        $error = null;
        $content = '<p>На данном шаге «Мастер установки» производит поиск таблицы персонажей и таблицы вещей.</p>';
        try {
            $model = Model::getInstance();
            $itemTable = $model->searchItemTableName();
            if ($itemTable) {
                ConfigWritter::getInstance()->setParameter('ITEM_TABLE', $itemTable);
            }

            $charTable = $model->searchCharsTableName();

            $content .=
                '<table class="table">'.
                    '<tr><td>Таблица вещей: '.($itemTable ? $itemTable : 'не найдена').'</td><td>'.
                    ( $itemTable ? '<span class="label label-success">OK</span>' :
                        '<span class="label label-important">ERROR</span>').'</td></tr>'.
                    '<tr><td>Таблица персонажей: '.($charTable ? $charTable : 'не найдена').'</td><td>'.
                    ( $charTable ? '<span class="label label-success">OK</span>' :
                        '<span class="label label-important">ERROR</span>').'</td></tr>'.
                '</table>';
            if (!$charTable || !$itemTable) {
                throw new Exception('Не найдены таблицы, трбуемые для корректной работы. '.
                    'Возможно у вас установлена еще не поддерживаемая (либо устаревшая) версия Lineage2. '.
                " Обратитесь в <a href='#' onclick=\"o=window.open;
                o('https://siteheart.com/webconsultation/519615?', 'siteheart_sitewindow_519615',
                'width=550,height=400,top=30,left=30,resizable=yes'); return false;\">службу поддержки</a>");
            }

            $content = '<div class="alert alert-success">Таблицы Lineage2 успешно найдены, можно переходить к следующему шагу</div>'.$content;

            ///'<p>Таблица с вещами:'.$itemType.'</p>';
        } catch (Exception $e) {
            $error = $e->getMessage();
            $content = '<div class="alert alert-error">'.$error.'</div>'.$content;
        }

        $this->loadView(array(
            'title' => 'Определение таблиц Lineage2',
            'content' => $content,
            'buttonText' => $error ?  'Повторить' : 'Продолжить',
            'action' => $error ? '?action=step3' : '?action=step4',
            'back' => '?action=step2',
            'method'    =>  'post'
        ));
    }

    /** Установка стоимости товара и цены */
    function step4()
    {
        $itemSave = false; $content = null;

        $config = ConfigWritter::getInstance();

        $itemPrice = $config->getParameter('ITEM_PRICE');
        $itemId = $config->getParameter('ITEM_ID');
        $secretKey = $config->getParameter('SECRET_KEY');
        $projectId = $config->getParameter('PROJECT_ID');
        if (!empty($_POST)) {
            $itemPrice = floatval($_POST['ITEM_PRICE']);
            $itemId = $_POST['ITEM_ID'];
            $secretKey = $_POST['SECRET_KEY'];
            $projectId = $_POST['PROJECT_ID'];


            $config->setParameter('ITEM_PRICE', $itemPrice);
            $config->setParameter('ITEM_ID', $itemId);
            $config->setParameter('SECRET_KEY', $secretKey);
            $config->setParameter('PROJECT_ID', intval($projectId));

            $content = '<div class="alert alert-success">Настройки успешно сохранены, можно переходить к следующему шагу</div>'.$content;
            $itemSave = true;
            header("Location: ?action=step5");
        }
        $content .= '
            <p>Установите ID товара за который будет взиматься оплата, его стоимость, а также секретный ключ и ID проекта из настроек в <a target="_blank" href="http://unitpay.ru">UnitPay.ru</a>.</p>
            <div><b>Настройка товара:</b></div>
            <div><label class="required" for="ITEM_ID">ID товара за который берется оплата</label><input type="text" value="'.$itemId.'" style="width:484px" required="required" name="ITEM_ID" id="ITEM_ID"></div>
            <div><label class="required" for="ITEM_PRICE">Стоимость единицы товара в рублях</label><input type="text" value="'.$itemPrice.'" style="width:484px" required="required" name="ITEM_PRICE" id="ITEM_PRICE"></div>
            <div><b>Привязка к <a target="_blank" href="http://unitpay.ru">UnitPay.ru</a>:</b></div>
            <div><label class="required" for="PROJECT_ID">Ваш ID проекта</label><input type="text" value="'.$projectId.'" style="width:484px" required="required" name="PROJECT_ID" id="PROJECT_ID"></div>
            <div><label class="required" for="SECRET_KEY">Ваш секретный ключ</label><input type="text" value="'.$secretKey.'" style="width:484px" required="required" name="SECRET_KEY" id="SECRET_KEY"></div>
            <p><a style="color:red; font-weight:bold; text-decoration:underline;" target="_blank" href="view/images/unitpay_sample.png">Где взять ID проекта и секретный ключ?</a></p>
        ';
        $this->loadView(array(
            'title' => 'Заключительные настройки',
            'content' => $content,
            'buttonText' => $itemSave ? 'Продолжить' : 'Сохранить',
            'action' => $itemSave ? '?action=step5' : '?action=step4',
            'back' => '?action=step3',
            'method'    =>  'post'
        ));
    }

    function step5()
    {
        $config = ConfigWritter::getInstance();

        $content = '<p>Поздравляем, скрипт успешно настроен, пропишие в настройках Вашего проекта на <a target="_blank" href="https://unitpay.ru/partner/project/'.$config->getParameter('PROJECT_ID')
            .'">UnitPay.ru</a>
        WEB-адрес обработчика:</p>';

        $url = 'http://'.$_SERVER["HTTP_HOST"].$_SERVER["SCRIPT_NAME"];
        $urlArr = explode('/', $url);
        array_pop($urlArr);
        $url = join('/', $urlArr).'/handler.php?test';
        $urlResp = json_decode(file_get_contents($url));

        if (is_object($urlResp)) {
            $content.= '<div class="alert alert-success">'.$url.'</div>';
        } else {
            $content.= '<div class="alert">К сожалению, мы не смогли автоматически определить абсолютный адрес handler.php,
            Вам нужно сформировать его вручную. Обычно это: <strong>http://ваш_сайт/папка_со_скриптами_unitpay/handler.php</strong>
            </div>
            ';
        }

        $content .= '<p>Все настройки сохранены в файле config.php, Вы всегда можете их отредактировать в случае необходимости.</p>';
        $content.= '
        <p><strong>Что дальше?</strong></p>
        <ul>
            <li>
                     <a style="font-weight:bold;text-decoration:underline" target="_blank" href="form-example.php#game1">
                        Разместите HTML-код формы оплаты
                    </a> на страницах вашего сайта. <span style="color:#888">Если Вы используете движок StressWeb, то можете разместить HTML-код прямо из админ-панели в требуемом разделе сайта.</span>
            </li>
            <li>
                Не забудьте удалить файл install.php <span class="label label-important">Важно!</span>
            </li>
        </ul>';

        $this->loadView(array(
            'title' => 'Настройка завершена',
            'content' => $content,
            'buttonText' => 'В Unitpay',
            'action' => 'http://unitpay.ru',
            'back' => '?action=step4',
            'method'    =>  'get'
        ));
    }

    function loadView($templateVars)
    {
        include 'view/form.html';
    }

    function process()
    {
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            $this->$action();
        } else {
            $this->welcome();
        }
    }
}


$L2Install = new Install();
$L2Install->process();


?>
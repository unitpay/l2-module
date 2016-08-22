<?php

include_once 'ConfigWritter.php';

class Model
{
    private $mysqli;

    static function getInstance()
    {
        return new self();
    }

    private function __construct()
    {
        $config = ConfigWritter::getInstance();

        $dbhost = $config->getParameter('DB_HOST');
        $dbname = $config->getParameter('DB_NAME');
        $dbpass = $config->getParameter('DB_PASS');
        $dbuser = $config->getParameter('DB_USER');

        if (empty($dbhost) || empty($dbname) || empty($dbuser)) {
            throw new Exception('Не установлены конфигурационные параметры для соединения с БД');
        }

        $this->mysqli = @new mysqli (
            $dbhost, $dbuser, $dbpass, $dbname
        );
        $this->mysqli->query("SET NAMES 'utf8'");

        /* проверка подключения */
        if (mysqli_connect_errno()) {
            throw new Exception('Не получается соединиться с БД, проверьте правильность параметров. Ошибка: ' . mysqli_connect_error());
        }
    }

    function searchItemTableName()
    {
        $type = null;
        if(mysqli_query($this->mysqli, 'select 1 from items_delayed') !== FALSE) {
            $type = 'items_delayed';
        } else if(mysqli_query($this->mysqli, 'select 1 from `character_items`') !== FALSE) {
            $type = 'character_items';
        } else if(mysqli_query($this->mysqli, 'select 1 from `items`') !== FALSE) {
            $type = 'items';
        }

        return $type;
    }
    function searchCharsTableName()
    {
        if(mysqli_query($this->mysqli, 'select 1 from `characters`') !== FALSE) {
            return 'characters';
        }

        return false;
    }

    function getChar($charName)
    {
        $query = '
                SELECT * FROM
                    characters
                WHERE
                    char_name = "'.$this->mysqli->real_escape_string($charName).'"
                LIMIT 1
            ';

        $result = $this->mysqli->query($query);

        if (!$result->num_rows) {
            //result
        }

        return $result->fetch_object();

    }

    function addItemToChar($charId, $itemId, $itemsCount = 1)
    {
        $itemTable = ConfigWritter::getInstance()->getParameter('ITEM_TABLE');
        switch ($itemTable) {
            case 'items_delayed':
                $result = $this->mysqli->query("SELECT max(payment_id) as maxId FROM items_delayed");
                $maxId = $result->fetch_object();
                $maxId = $maxId->maxId;

                return
                    $this->mysqli->query("INSERT INTO `items_delayed` (`payment_id`, `owner_id`, `item_id`, `count`, `payment_status`, `description`)
                        VALUES ('".($maxId+1)."', '".$charId."', '".$itemId."', '".$itemsCount."', '0', 'Unitpay')");
                break;

            case 'character_items':
                $query = '
                INSERT INTO
                    character_items (owner_id, item_id, count, enchant_level)
                VALUES
                    (
                        "'.$this->mysqli->real_escape_string($charId).'",
                        "'.$this->mysqli->real_escape_string($itemId).'",
                        "'.$this->mysqli->real_escape_string($itemsCount).'",
                        0
                    )
                ';

                return $this->mysqli->query($query);
                break;

            case 'items_external':
                $query = '
                INSERT INTO
                    items_external (owner_id, item_id, count, enchant, message, description, issued)
                VALUES
                    (
                        "'.$this->mysqli->real_escape_string($charId).'",
                        "'.$this->mysqli->real_escape_string($itemId).'",
                        "'.$this->mysqli->real_escape_string($itemsCount).'",
                        0,
						"Благодарим за поддержку проекта! Бонус выдан",
						"UnitPay",
						0
                    )
                ';

                return $this->mysqli->query($query);
                break;

            case 'items':
            default:

                $query = '
                        SELECT * FROM
                            items
                        WHERE
                            item_id = "'.$this->mysqli->real_escape_string($itemId).'" and
                            owner_id = "'.$this->mysqli->real_escape_string($charId).'"
                        LIMIT 1
                    ';
                $item = $this->mysqli->query($query)->fetch_object();

                // Если предмет у персонажа есть, то наращиваем его
                if ($item && $item->count) {
                    $query = '
                    UPDATE
                        items
                    SET
                        count = "'.($item->count + $itemsCount).'"
                    WHERE
                        object_id = "'.$item->object_id.'"';
                // В противном случае создаем новый в его инвентаре
                } else {
                    $result = $this->mysqli->query("SELECT max(object_id) as maxId FROM items");
                    $maxId = $result->fetch_object();
                    $maxId = $maxId->maxId;

                    $query = '
                    INSERT INTO
                        items (object_id, owner_id, item_id, count, enchant_level, loc)
                    VALUES
                        (
                            "'.($maxId+1).'",
                            "'.$this->mysqli->real_escape_string($charId).'",
                            "'.$this->mysqli->real_escape_string($itemId).'",
                            "'.$this->mysqli->real_escape_string($itemsCount).'",
                            0,
                            "INVENTORY"
                        )
                    ';
                }

                return $this->mysqli->query($query);

        }
    }

    function removeItemFromChar($charId, $itemId, $itemsCount = 1)
    {
        $itemTable = ConfigWritter::getInstance()->getParameter('ITEM_TABLE');
        switch ($itemTable) {
            case 'items_delayed':
                    $sql = "
                        DELETE FROM
                          items_delayed
                        WHERE
                          owner_id = ".$this->mysqli->real_escape_string($charId)." AND
                          item_id = ".$this->mysqli->real_escape_string($itemId)." AND
                          count = ".$this->mysqli->real_escape_string($itemsCount)."
                        LIMIT 1
                    ";

                return $this->mysqli->query($sql);
                break;

            case 'character_items':
                    $sql = "
                        DELETE FROM
                          character_items
                        WHERE
                          owner_id = ".$this->mysqli->real_escape_string($charId)." AND
                          item_id = ".$this->mysqli->real_escape_string($itemId)." AND
                          count = ".$this->mysqli->real_escape_string($itemsCount)."
                        LIMIT 1
                    ";

                    return $this->mysqli->query($sql);
                break;

            case 'items_external':
                $sql = "
                        DELETE FROM
                          items_external
                        WHERE
                          owner_id = ".$this->mysqli->real_escape_string($charId)." AND
                          item_id = ".$this->mysqli->real_escape_string($itemId)." AND
                          count = ".$this->mysqli->real_escape_string($itemsCount)."
                        LIMIT 1
                    ";

                return $this->mysqli->query($sql);
                break;

            case 'items':
            default:
                $sql = '
                    SELECT * FROM
                        items
                    WHERE
                        item_id = "'.$this->mysqli->real_escape_string($itemId).'" and
                        owner_id = "'.$this->mysqli->real_escape_string($charId).'"
                    LIMIT 1
                ';

                $item = $this->mysqli->query($sql)
                    ->fetch_object();

                if ($item && $item->count)
                {
                    $query = '
                        UPDATE
                            items
                        SET
                            count = "'.($item->count - $itemsCount).'"
                        WHERE
                            object_id = "'.$item->object_id.'"';

                        return $this->mysqli->query($query);
                }

                return;
                break;
        }
    }

    function createPayment($unitpayId, $charId, $sum, $itemsCount)
    {
        $query = '
                INSERT INTO
                    unitpay_payments (unitpayId, account, sum, itemsCount, dateCreate, status)
                VALUES
                    (
                        "'.$this->mysqli->real_escape_string($unitpayId).'",
                        "'.$this->mysqli->real_escape_string($charId).'",
                        "'.$this->mysqli->real_escape_string($sum).'",
                        "'.$this->mysqli->real_escape_string($itemsCount).'",
                        NOW(),
                        0
                    )
            ';

        return $this->mysqli->query($query);
    }

    function  cancelPaymentByUnitpayId($unitpayId)
    {
        $query = '
                UPDATE
                    unitpay_payments
                SET
                    status = 2,
                    dateComplete = NOW()
                WHERE
                    unitpayId = "'.$this->mysqli->real_escape_string($unitpayId).'"
                LIMIT 1
            ';
        return $this->mysqli->query($query);
    }

    function getPaymentByUnitpayId($unitpayId)
    {
        $query = '
                SELECT * FROM
                    unitpay_payments
                WHERE
                    unitpayId = "'.$this->mysqli->real_escape_string($unitpayId).'"
                LIMIT 1
            ';
        $result = $this->mysqli->query($query);

        return $result->fetch_object();
    }

    function confirmPaymentByUnitpayId($unitpayId)
    {
        $query = '
                UPDATE
                    unitpay_payments
                SET
                    status = 1,
                    dateComplete = NOW()
                WHERE
                    unitpayId = "'.$this->mysqli->real_escape_string($unitpayId).'"
                LIMIT 1
            ';
        return $this->mysqli->query($query);
    }
}
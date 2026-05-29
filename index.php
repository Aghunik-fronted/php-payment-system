<?php

interface Payable {
    public function pay(float $amount): bool;
    public function getPaymentMethod(): string;
    public function validate(): bool;
}

trait Loggable {
    private array $logs = [];

    public function log(string $message): void {
        $date = date('Y-m-d H:i:s');
        $this->logs[] = "[$date] $message";
    }

    public function getLogs(): array {
        return $this->logs;
    }

    public function getLastLog(): string {
        return empty($this->logs) ? '' : end($this->logs);
    }
}

abstract class Payment implements Payable {
    use Loggable;

    protected string $ownerName;
    protected float $balance;

    public function __construct(string $ownerName, float $balance) {
        $this->ownerName = $ownerName;
        $this->balance = $balance;
    }

    #[Override]
    public function getPaymentMethod(): string {
        return get_class($this);
    }

    #[Override]
    public function validate(): bool {
        if (trim($this->ownerName) === '' || mb_strlen($this->ownerName) < 3) {
            $this->log("Ошибка: имя владельца слишком короткое или пустое");
            return false;
        }
        return true;
    }

    #[Override]
    public function pay(float $amount): bool {
        if (!$this->validate()) {
            return false;
        }

        if ($amount <= 0) {
            $this->log("Ошибка: сумма должна быть больше 0");
            return false;
        }

        if ($this->balance < $amount) {
            $this->log("Ошибка: недостаточно средств");
            return false;
        }

        if ($this->doPay($amount)) {
            $this->balance -= $amount;
            $this->log("Платёж {$amount} руб. выполнен через {$this->getPaymentMethod()}");
            return true;
        }
        return false;
    }
    abstract protected function doPay(float $amount): bool;
}

class CardPayment extends Payment {
    private string $cardNumber;
    private string $expiryDate;
    private string $cvv;

    public function __construct(string $ownerName, float $balance, string $cardNumber, string $expiryDate, string $cvv) {
        parent::__construct($ownerName, $balance); 
        $this->cardNumber = $cardNumber;
        $this->expiryDate = $expiryDate;
        $this->cvv = $cvv;
    }

    #[Override]
    public function validate(): bool {
        if (!parent::validate()) {
            return false;
        }

        if (!preg_match('/^\d{16}$/', $this->cardNumber)) {
            $this->log("Ошибка: неверный формат номера карты (должно быть 16 цифр)");
            return false;
        }

        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $this->expiryDate)) {
            $this->log("Ошибка: неверный формат срока действия (ожидается MM/YY)");
            return false;
        }

        if (!preg_match('/^\d{3}$/', $this->cvv)) {
            $this->log("Ошибка: неверный формат CVV (должно быть 3 цифры)");
            return false;
        }

        return true;
    }

    protected function doPay(float $amount): bool {
        return true;
    }
}

class WalletPayment extends Payment {
    private string $phoneNumber;
    private string $pinCode;

    public function __construct(string $ownerName, float $balance, string $phoneNumber, string $pinCode) {
        parent::__construct($ownerName, $balance);
        $this->phoneNumber = $phoneNumber;
        $this->pinCode = $pinCode;
    }

    #[Override]
    public function validate(): bool {
        if (!parent::validate()) {
            return false;
        }

        if (!preg_match('/^\d{11}$/', $this->phoneNumber)) {
            $this->log("Ошибка: неверный формат телефона (должно быть 11 цифр)");
            return false;
        }

        if (!preg_match('/^\d{4}$/', $this->pinCode)) {
            $this->log("Ошибка: неверный формат PIN-кода (должно быть 4 цифры)");
            return false;
        }

        return true;
    }

    protected function doPay(float $amount): bool {
        return true; 
    }
}

echo "<pre>";

$payments = [
    new CardPayment("Иван Иванов", 2000.00, "1234567812345678", "12/28", "123"),
    new WalletPayment("Петр Петров", 1000.00, "79991112233", "4321"),
    new CardPayment("Иван Иванов", 500.00, "1234567812345678", "12/28", "123") 
];

$testAmounts = [1500.00, 500.00, 2000.00];
$allLogs = [];

foreach ($payments as $index => $payment) {
    $amount = $testAmounts[$index];
    $method = $payment->getPaymentMethod();

    echo "Платёж {$amount} руб. через {$method}... ";
    
    $success = $payment->pay($amount);
    
    if ($success) {
        echo "Успех\n";
    } else {
        $lastLog = $payment->getLastLog();
        $errorText = trim(substr($lastLog, strpos($lastLog, ']') + 1));
        echo "{$errorText}\n";
    }

    $allLogs = array_merge($allLogs, $payment->getLogs());
    echo "Последний лог: " . $payment->getLastLog() . "\n\n";
}

echo "Все логи:\n";
foreach ($allLogs as $key => $logLine) {
    $itemNumber = $key + 1;
    echo "{$itemNumber}. {$logLine}\n";
}
echo "</pre>";
?>
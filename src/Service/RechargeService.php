<?php

namespace William\HyperfExtTelegram\Service;


use App\Bot\ErrorHandler\OrderNotFound;
use Carbon\Carbon;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use William\HyperfExtTelegram\Constants;
use William\HyperfExtTelegram\Core\BotManager;
use William\HyperfExtTelegram\Core\Instance;
use William\HyperfExtTelegram\Events;
use William\HyperfExtTelegram\Helper\BotHelper;
use William\HyperfExtTelegram\Helper\Logger;
use William\HyperfExtTelegram\Model\TelegramUser;
use William\HyperfExtTelegram\Model\UserRechargeOrder;

class RechargeService
{
    public function __construct(protected AmountPoolService $amountPoolService)
    {
    }

    public function getSwitchButton($secretKey, $payCurrency, $orderId, $buttonKey, $qc)
    {
        $switchToCurrency = $payCurrency === 'TRX' ? 'USDT' : 'TRX';
        BotHelper::newButton($secretKey, $buttonKey, ['to_currency' => $switchToCurrency],
            $qc, [
                'to' => $switchToCurrency,
                'id' => $orderId
            ]);
    }

    public function switchCurrency($orderId, $toCurrency): ?UserRechargeOrder
    {
        $order = UserRechargeOrder::find($orderId);
        if (!$order) {
            return null;
        }
        $toCurrency = strtolower($toCurrency);
        $amountDetail = $order->amount_detail;
        $toAmount = $amountDetail[$toCurrency];
        $order->amount = $toAmount;
        $order->currency = $toCurrency;
        $order->save();
        return $order;
    }

    public function createOrder(float $inputAmount, Instance $instance): UserRechargeOrder
    {
        $amount = $this->amountPoolService->getUniqueAmount($inputAmount, 4, 30);
        Logger::debug("生成随机金额：$amount");
        $trxAmount = BotHelper::usdtToTrx($amount);
        // 生成订单号
        $orderNo = BotHelper::generateOrderId(); // 自定义函数

        // 格式化金额
        $amountFormatted = BotHelper::formatAmount($amount);
        $trxAmountFormatted = BotHelper::formatAmount($trxAmount);
        /** @var TelegramUser $user */
        $user = $instance->getCurrentUser();
        // 创建订单
        $order = new UserRechargeOrder();
        $order->user_id = $user->id;
        $order->order_no = $orderNo;
        $order->amount = $amount;
        $order->actual_amount = $inputAmount;
        $order->currency = 'usdt';
        $order->amount_detail = [
            'usdt' => $amountFormatted,
            'trx' => $trxAmountFormatted,
        ];
        $order->status = UserRechargeOrder::PENDING; // 假设你有枚举常量
        $order->to_address = BotHelper::getRechargeAddress(); // 自定义函数
        $order->expired_at = Carbon::now()->addMinutes(90);
        $order->save(); // 提交到数据库

        // 如果你想使用刷新后的数据
        $order->refresh(); // Eloquent 提供的刷新实例方法
        return $order;
    }

    /**
     * @throws \Exception
     */
    public function cancelOrder(mixed $id): void
    {
        $order = UserRechargeOrder::find($id);
        if (!$order) {
            throw new \Exception('error_order_not_found');
        }
        $order->status = UserRechargeOrder::CANCELED;
        $order->save();
    }

    public function orderExpired(UserRechargeOrder $order)
    {
        $order->status = UserRechargeOrder::EXPIRED;
        $order->save();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \RedisException
     */
    public function checkPay(array $tx)
    {
        /** @var BotManager $botManager */
        $botManager = ApplicationContext::getContainer()->get(BotManager::class);
        $rechargeOrder = UserRechargeOrder::where('amount', $tx['amount'])
            ->where('currency', strtolower($tx['currency']))
            ->first();
        if ($rechargeOrder) {
            Logger::debug('充值到账');
            $user = TelegramUser::find($rechargeOrder->user_id);
            $botManager->dispatch(
                $user->bot_id,
                Events::RechargeSuccess,
                ['order_id' => $rechargeOrder->id, 'tx' => $tx]
            );
        }
    }
}
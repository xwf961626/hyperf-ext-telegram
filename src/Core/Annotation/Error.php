<?php

namespace William\HyperfExtTelegram\Core\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS)]
class Error extends AbstractAnnotation
{
    const InsufficientBalance = 'insufficient balance';
    const InvalidTronAddress = 'invalid tron address';
    const OrderNotFound = 'order not found';
    const OrderStatusError = 'order status error';
    const SystemError = 'system error';
    const AddressNotFound = 'address not found';

    public function __construct(public string $error)
    {

    }
}
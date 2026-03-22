<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Attributes\Concurrency;
use Vinkius\Vurb\Attributes\FsmBind;
use Vinkius\Vurb\Attributes\Instructions;
use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbMutation;

#[Instructions('Only call after confirming payment details with the user.')]
#[Concurrency(max: 3)]
#[FsmBind(states: ['payment'], event: 'PAY')]
class ProcessPayment extends VurbMutation
{
    public function description(): string
    {
        return 'Process a payment for an order.';
    }

    public function handle(
        #[Param(description: 'Order ID')] int $order_id,
        #[Param(description: 'Amount in cents')] int $amount,
        #[Param(description: 'Payment method')] string $method = 'card',
    ): array {
        return [
            'order_id' => $order_id,
            'amount' => $amount,
            'method' => $method,
            'status' => 'completed',
            'transaction_id' => 'txn_test_123',
        ];
    }
}

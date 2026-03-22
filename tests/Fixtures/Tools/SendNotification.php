<?php

namespace Vinkius\Vurb\Tests\Fixtures\Tools;

use Vinkius\Vurb\Attributes\Param;
use Vinkius\Vurb\Tools\VurbAction;

class SendNotification extends VurbAction
{
    public function description(): string
    {
        return 'Send a notification to a user.';
    }

    public function handle(
        #[Param(description: 'User ID')] int $user_id,
        #[Param(description: 'Message body')] string $message,
        #[Param(description: 'Notification channels', items: 'string')] array $channels,
    ): array {
        return [
            'sent' => true,
            'user_id' => $user_id,
            'channels' => $channels,
        ];
    }
}

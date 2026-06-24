<?php

namespace App\Support\Reviews;

use InvalidArgumentException;

final class ProductReviewModeration
{
    public static function vendorReplySnapshot(?int $vendorUserId, ?int $vendorId, string $body): array
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Reply body is required.');
        }

        return [
            'Reply_Type' => 'vendor',
            'Vendor_User_Id' => $vendorUserId,
            'Vendor_Id' => $vendorId,
            'Body' => $body,
            'Status' => 'approved',
        ];
    }
}

<?php

namespace Modules\User\Domain\Entities\OAuth;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenCommonTrait;

class AuthCodeEntity implements AuthCodeEntityInterface
{
    use AuthCodeTrait;
    use EntityTrait;
    use TokenCommonTrait;
}

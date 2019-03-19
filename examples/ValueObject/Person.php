<?php
declare(strict_types=1);

namespace ProophExample\ValueObject;

use EventEngine\Data\ImmutableRecord;
use EventEngine\Data\ImmutableRecordLogic;

final class Person implements ImmutableRecord
{
    use ImmutableRecordLogic;

    public const USER_ID = 'userId';
    public const NAME = 'name';
    public const AGE = 'age';
    public const FRIENDS = 'friends';

    /**
     * @var FriendsList
     */
    private $friends;


    /**
     * @var Age|null
     */
    private $age;

    /**
     * @var GivenName
     */
    private $name;

    /**
     * @var UserId
     */
    private $userId;

    public static function register(GivenName $givenName): self
    {
        return self::fromRecordData([
            self::USER_ID => UserId::generate(),
            self::NAME => $givenName
        ]);
    }

    private function init(): void
    {
        if(null === $this->friends) {
            $this->friends = FriendsList::emptyList();
        }
    }

    /**
     * @return FriendsList
     */
    public function friends(): FriendsList
    {
        return $this->friends;
    }

    /**
     * @return Age|null
     */
    public function age(): ?Age
    {
        return $this->age;
    }

    /**
     * @return GivenName
     */
    public function name(): GivenName
    {
        return $this->name;
    }

    /**
     * @return UserId
     */
    public function userId(): UserId
    {
        return $this->userId;
    }
}

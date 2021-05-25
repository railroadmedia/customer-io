<?php

namespace Railroad\CustomerIo\ValueObjects;

use Carbon\Carbon;

class Customer
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * @var string
     */
    private $email;

    /**
     * @var array
     */
    private $customAttributes = [];

    /**
     * @var Carbon
     */
    private $createdAt;

    /**
     * Customer constructor.
     * @param  string  $uuid
     * @param  string  $email
     * @param  array  $customAttributes
     * @param  Carbon|null  $createdAt
     */
    public function __construct(string $uuid, string $email, array $customAttributes, Carbon $createdAt = null)
    {
        $this->uuid = $uuid;
        $this->email = $email;
        $this->customAttributes = $customAttributes;
        $this->createdAt = $createdAt ?? Carbon::now();
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @param  string  $uuid
     */
    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param  string  $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return array
     */
    public function getCustomAttributes(): array
    {
        return $this->customAttributes;
    }

    /**
     * @param  array  $customAttributes
     */
    public function setCustomAttributes(array $customAttributes): void
    {
        $this->customAttributes = $customAttributes;
    }

    /**
     * @return Carbon
     */
    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    /**
     * @param  Carbon  $createdAt
     */
    public function setCreatedAt(Carbon $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
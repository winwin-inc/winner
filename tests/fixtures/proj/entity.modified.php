<?php

declare(strict_types=1);

namespace winwin\admin\center\domain\entity;

use kuiper\db\annotation\CreationTimestamp;
use kuiper\db\annotation\GeneratedValue;
use kuiper\db\annotation\Id;
use kuiper\db\annotation\NaturalId;
use kuiper\db\annotation\UpdateTimestamp;

class UserRole
{
    /**
     * @Id
     * @GeneratedValue
     *
     * @var int|null
     */
    private $id;
    /**
     * @CreationTimestamp
     *
     * @var \DateTimeInterface|null
     */
    private $createTime;
    /**
     * @UpdateTimestamp
     *
     * @var \DateTimeInterface|null
     */
    private $updateTime;
    /**
     * @NaturalId
     *
     * @var string|null
     */
    private $userId;
    /**
     * @NaturalId
     *
     * @var int|null
     */
    private $roleId;
    /**
     * @var bool|null
     */
    private $deleted;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return \DateTime|null
     */
    public function getCreateTime(): ?\DateTimeInterface
    {
        return $this->createTime;
    }

    /**
     * @param \DateTime|null $createTime
     */
    public function setCreateTime(?\DateTimeInterface $createTime): void
    {
        $this->createTime = $createTime;
    }

    /**
     * @return \DateTime|null
     */
    public function getUpdateTime(): ?\DateTimeInterface
    {
        return $this->updateTime;
    }

    /**
     * @param \DateTime|null $updateTime
     */
    public function setUpdateTime(?\DateTimeInterface $updateTime): void
    {
        $this->updateTime = $updateTime;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getRoleId(): ?int
    {
        return $this->roleId;
    }

    public function setRoleId(?int $roleId): void
    {
        $this->roleId = $roleId;
    }

    public function getDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(?bool $deleted): void
    {
        $this->deleted = $deleted;
    }
}

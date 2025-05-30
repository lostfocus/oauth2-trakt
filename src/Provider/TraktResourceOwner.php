<?php namespace Lostfocus\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Tool\ArrayAccessorTrait;

class TraktResourceOwner implements ResourceOwnerInterface
{
    use ArrayAccessorTrait;

    /**
     * Raw response
     *
     * @var array
     */
    protected array $response = [];

    /**
     * Creates new resource owner.
     *
     * @param  array  $response
     */
    public function __construct(array $response = [])
    {
        $this->response = $response;
    }

    /**
     * Get username
     *
     * @return string|null
     */
    public function getUsername(): ?string
    {
        $username = $this->getValueByKey($this->response, 'user.username');
        if (!is_string($username)) {
            return null;
        }

        return $username;
    }

    /**
     * Get user name
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        $name = $this->getValueByKey($this->response, 'user.name');
        if (!is_string($name)) {
            return null;
        }

        return $name;
    }

    /**
     * Get user avatar url
     *
     * @return string|null
     */
    public function getAvatarUrl(): ?string
    {
        $avatarUrl = $this->getValueByKey($this->response, 'user.images.avatar.full');
        if (!is_string($avatarUrl)) {
            return null;
        }

        return $avatarUrl;
    }

    /**
     * Get user slug
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        $id = $this->getValueByKey($this->response, 'user.ids.slug');
        if (!is_string($id)) {
            return null;
        }

        return $id;
    }

    /**
     * Return all of the owner details available as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->response;
    }
}

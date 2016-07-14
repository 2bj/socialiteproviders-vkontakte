<?php

namespace SocialiteProviders\VKontakte;

use Laravel\Socialite\Two\User;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\InvalidStateException;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * {@inheritdoc}
     */
    protected $scopes = ['email'];

    /**
     * The user fields being requested.
     *
     * @var array
     */
    protected $fields = ['uid', 'first_name', 'last_name', 'screen_name', 'photo'];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            'https://oauth.vk.com/authorize', $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://oauth.vk.com/access_token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            sprintf('https://api.vk.com/method/users.get?user_ids=%s&fields=%s',
                $token['user_id'],
                implode(',', $this->fields)
            )
        );

        $response = json_decode($response->getBody()->getContents(), true)['response'][0];

        return array_merge($response, [
            'email' => array_get($token, 'email'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['uid'],
            'nickname' => $user['screen_name'],
            'name'     => $user['first_name'] . ' ' . $user['last_name'],
            'email'    => array_get($user, 'email'),
            'avatar'   => $user['photo'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessToken($body)
    {
        return json_decode($body, true);
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $user = $this->mapUserToObject($this->getUserByToken(
            $token = $this->getAccessTokenResponse($this->getCode())
        ));

        return $user->setToken(array_get($token, 'access_token'));
    }

    /**
     * Set the user fields to request.
     *
     * @param  array $fields
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Append the user field to fields.
     *
     * @param $field
     * @return $this
     */
    public function appendField($field)
    {
        $this->fields[] = $field;

        return $this;
    }
}

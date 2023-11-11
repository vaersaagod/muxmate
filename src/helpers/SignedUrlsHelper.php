<?php

namespace vaersaagod\muxmate\helpers;

use Craft;

use Firebase\JWT\JWT;

use Firebase\JWT\Key;
use vaersaagod\muxmate\models\MuxPlaybackId;
use vaersaagod\muxmate\models\MuxSigningKey;
use vaersaagod\muxmate\MuxMate;

final class SignedUrlsHelper
{

    /** @var string */
    public const SIGNED_URL_AUDIENCE_THUMBNAIL = 't';

    /** @var string */
    public const SIGNED_URL_AUDIENCE_VIDEO = 'v';

    /** @var string */
    public const SIGNED_URL_AUDIENCE_GIF = 'g';

    /** @var string */
    public const SIGNED_URL_AUDIENCE_STORYBOARD = 's';

    /**
     * @param string|MuxPlaybackId $playbackId
     * @param string $aud
     * @param array|null $claims
     * @param int|null $expirationInSeconds
     * @param bool|null $returnPlaceholder
     * @return string|null
     */
    public static function getToken(string|MuxPlaybackId $playbackId, string $aud, ?array $claims = null, ?int $expirationInSeconds = null, ?bool $returnPlaceholder = null): ?string
    {

        if ($playbackId instanceof MuxPlaybackId) {
            if ($playbackId->policy !== MuxMateHelper::PLAYBACK_POLICY_SIGNED) {
                return null;
            }
            $playbackId = $playbackId->__toString();
        }

        if (empty($playbackId)) {
            Craft::error("Empty playback ID", __METHOD__);
            return null;
        }

        if (!in_array($aud, [
            SignedUrlsHelper::SIGNED_URL_AUDIENCE_THUMBNAIL,
            SignedUrlsHelper::SIGNED_URL_AUDIENCE_VIDEO,
            SignedUrlsHelper::SIGNED_URL_AUDIENCE_GIF,
            SignedUrlsHelper::SIGNED_URL_AUDIENCE_STORYBOARD,
        ], true)) {
            Craft::error("Invalid audience key \"$aud\"", __METHOD__);
            return null;
        }

        $signingKey = SignedUrlsHelper::getSigningKey();
        if (!$signingKey) {
            return null;
        }

        // Make sure the expiration time is minimum the default
        $minExpirationTime = $signingKey->minExpirationTime;
        if (empty($expirationInSeconds) || $expirationInSeconds < $minExpirationTime) {
            $expirationInSeconds = $minExpirationTime;
        }

        // Maybe return a placeholder token
        if ($returnPlaceholder !== false && Craft::$app->getRequest()->getIsSiteRequest() && Craft::$app->getConfig()->getGeneral()->enableTemplateCaching) {
            return SignedUrlsHelper::_getPlaceholderToken([
                'playbackId' => $playbackId,
                'aud' => $aud,
                'claims' => $claims,
                'expirationInSeconds' => $expirationInSeconds,
            ]);
        }

        $claims = array_merge([
            'sub' => $playbackId,
            'exp' => time() + $expirationInSeconds,
            'kid' => $signingKey->id,
            'aud' => $aud,
        ], $claims ?? []);

        return JWT::encode($claims, base64_decode($signingKey->privateKey), 'RS256');

    }

    /**
     * @return MuxSigningKey|null
     */
    public static function getSigningKey(): ?MuxSigningKey
    {
        $signingKey = MuxMate::getInstance()->getSettings()->muxSigningKey;
        if (!$signingKey instanceof MuxSigningKey || !$signingKey->validate()) {
            Craft::error("Invalid Mux signing key", __METHOD__);
            return null;
        }
        return $signingKey;
    }

    /**
     * @param string $token A hashed JWT representing a placeholder token
     * @return array|null
     */
    public static function decodePlaceholderToken(string $token): ?array
    {
        if (!$signingKey = SignedUrlsHelper::getSigningKey()) {
            return null;
        }
        try {
            $token = Craft::$app->getSecurity()->validateData($token);
            return (array)JWT::decode($token, new Key($signingKey->id, 'HS256'));
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
        }
        return null;
    }

    /**
     * @param array $payload
     * @return string|null
     */
    private static function _getPlaceholderToken(array $payload = []): ?string
    {
        if (!$signingKey = SignedUrlsHelper::getSigningKey()) {
            return null;
        }
        try {
            $placeholderToken = Craft::$app->getSecurity()->hashData(JWT::encode($payload, $signingKey->id, 'HS256'));
            return "MUX_TOKEN_PLACEHOLDER{$placeholderToken}MUX_TOKEN_PLACEHOLDER";
        } catch (\Throwable $e) {
            Craft::error($e, __METHOD__);
            return null;
        }
    }

}

# MuxMate 3.x  

Mux ado about streaming, mate!

<img src="https://github.com/vaersaagod/muxmate/blob/main/src/icon.svg" width="200" height="200" alt="Logo">

## Description  

MuxMate integrates [Mux](https://www.mux.com/) with Craft CMS.    

## Requirements

This plugin requires Craft CMS 5.2.0 or later, and PHP 8.2 or later.

## Disclaimer

This is a [private plugin](https://craftcms.com/docs/5.x/extend/plugin-guide.html#private-plugins), made for Værsågod and friends.

## Getting started  

### 1. Create a Mux environment

Register a Mux account on https://www.mux.com, and add an environment. _It's a good idea to have separate Mux environments for each Craft environment._   

### 2. Create a Mux access token

Create a Mux access token for the appropriate Mux environment. The token only needs the "Mux Video" permission.    
Copy the access token ID and secret key.  

### 3. Configure MuxMate

Create a file `config/_muxmate.php` in your repository, and add the Mux access token ID and secret key:

```php
<?php

use craft\helpers\App;

return [
    'muxAccessTokenId' => App::env('MUX_ACCESS_TOKEN_ID'),
    'muxSecretKey' => App::env('MUX_SECRET_KEY'),
];
```  

...there are heaps more config settings (see [below](#configuration) for all of them), but those two are the only ones you'll *definitely* need.

### 4. Add the MuxMate webhook endpoint to the Mux environment

You're going to have a hard time without a working Mux webhook, so don't skip this step!  

In your Mux account, visit Settings -> Webhooks, select the appropriate environment, and create a new webhook with this URL:  

`https://your-site.com/muxmate/webhook`  

_If this is a local environment (or otherwise not accessible from outside), you can use Ngrok or similar to create a publicly available Webhook endpoint URL._  

### 5. Create a MuxMate field and add it to a volume

MuxMate ships with a custom "MuxMate" field type. Create a field with this field type (name or handle doesn't matter), and add it to the field layout for any volume(s) where you plan on uploading your videos.

### 6. Upload a video to Craft, and wait for it

When a video (with a MuxMate field in its field layout) is saved in Craft, it's URL is automatically POSTed to Mux's `assets/create` endpoint. Assuming the video's URL is publicly available, Mux then downloads the video and creates a Mux asset for it.  

The process on Mux' end is completely asynchronous, but follows a pre-determined order:  

1. Mux asset is created, with basic metadata
2. HLS stream is created; Mux asset status changes to "ready" when this is ready
3. Static renditions (i.e. transpiled MP4 assets) are created; the Mux asset metadata will contain a key `static_renditions` when this is done.  

**Assuming the MuxMate webhook URL has been added to Mux (and is publicly available), the asset's Mux meta data in Craft will be automatically updated for each of the above steps.**  

### 7. Render the Mux video on the front end

To render a video that has been created in Mux, simply call the `getMuxVideo()` method on the asset to render a custom [`<mux-video>`](https://github.com/muxinc/elements/tree/main/packages/mux-video) web component instance:  

```twig
{{ video.getMuxVideo()|attr({ controls: true }) }}
```  

## Local testing

In local environments, Mux typically won't be able to access your videos. To make sure it can, either  

### Use a cloud storage (S3, DO Spaces) file system for your video assets  

A simple solution is to use some sort of cloud storage file system for your videos' volume. You can even set up a cloud storage file system that is only used locally, by using an environment variable for your volume's "Asset Filesystem" setting.  

### Override your volume's file system Base URL in local environments with a remote tunnel

If you've already set up something like an Ngrok tunnel for your webhook, you can also use that same tunnel to ensure Mux is provided access to your video assets. Here's a couple of ways to do that:    

#### Override the file system's Base URL using MuxMate

You can configure MuxMate to override the base URL for assets in a particular volume, by using the MuxMate `volumes` config setting.  

Assuming your videos live in a volume with the handle `videos` (which is using a file system with the Base URL set to `@web/uploads/videos`), here's how you could override that Base URL with your Ngrok URL:  

In your `.env` file:  
```shell
VIDEO_VOLUME_BASE_URL=https://2d13-51-175-241-107.ngrok-free.app/uploads/videos
```

In your `config/_muxmate.php` file:  

```php
<?php

use craft\helpers\App;

return [
    'muxAccessTokenId' => App::env('MUX_ACCESS_TOKEN_ID'),
    'muxSecretKey' => App::env('MUX_SECRET_KEY'),
    'volumes' => [
        'videos' => [
            'baseUrl' => App::env('VIDEO_VOLUME_BASE_URL'),
        ],
    ],
];
```

#### Set the file system's Base URL to a custom alias

Another option is to add a custom Yii alias, for example something like `@videosBaseUrl`, and set your volume's file system's "Base URL" setting to that alias (for example, `@videosBaseUrl/uploads/videos`). Then, define the custom `@videosBaseUrl` alias using the `aliases` Craft config setting:       

In your `.env` file:  
```shell
VIDEOS_BASE_URL=https://2d13-51-175-241-107.ngrok-free.app
```

In your `config/general.php` file: 
```php
'aliases' => [
    '@videosBaseUrl' => App::env('VIDEOS_ALIAS') ?? App::env('PRIMARY_SITE_URL')
],
```
## Creating (or updating) Mux assets for existing Craft video assets  

MuxMate assets are created when videos are uploaded to Craft, and simply adding a MuxMate field to a volume won't do anything, in terms of any existing video assets in that volume. If you want MuxMate to create (or update) Mux assets for existing Craft video assets, there are a few options:  

### Re-save the asset (manually, via the control panel)  
MuxMate does not create or update Mux assets when Craft assets are re-saved programmatically (this is on purpose). But, resaving an asset manually via the control panel will do it.

### Click the "Create Mux asset" button on the asset edit page.  
Unlike resaving the asset manually, this will not update the asset's `dateUpdated` attribute, to the extent that matters.

### Use the `_muxmate/create` CLI command  
The `_muxmate/create` CLI command can be used to create Mux assets, both in bulk (i.e. for an entire volume) or for a single asset:     

`_muxmate/create` - Create Mux assets for all video assets. Pass `--update=1` to also re-create any existing Mux assets.  
`_muxmate/create --volume=videos` - Create Mux assets for all video assets in a volume `videos`. Pass `--update=1` Pass `--update=1` to also re-create any existing Mux assets.    
`_muxmate/create --assetId=1234` - Create a Mux asset for a video asset with the ID `1234`. Pass `--update=1` Pass `--update=1` to also re-create an existing Mux asset.   

## Rendering and playing videos

### Mux video

The easiest way to render Mux videos is by using the `getMuxVideo()` method, which is available to all assets:  

```twig
{{ video.getMuxVideo() }}
```  

The above will render a `<mux-video>` web component, which is a near-drop in replacement for the native `<video>` element.  
The good thing about `<mux-video>` is that it automatically polyfills HLS if the user's browser doesn't support it, and that it automatically integrates with Mux Data.  

#### Setting `<mux-video>` attributes  

The `|attr()` Twig filter can be used to set attributes on the `<mux-video>` component:  

```twig
{{ video.getMuxVideo()|attr({
    id: 'my-video',
    class: 'border-2 border-black',
    controls: true
}) }}
```

#### Inline video (playsinline/video loops)

To render an inline video (i.e. video loop), you can pass a parameter `inline: true` to the `getMuxVideo()` method:  

```twig
{{ video.getMuxVideo({ inline: true }) }}
```

The above automatically sets the following attributes on the `<mux-video>` component:  

```
autoplay: 'muted',
loop: true,
muted: true,
playsinline: true,
disablePictureInPicture: true
```

Alternatively, you can of course set these attributes yourself using the `|attr()` Twig filter:  

```twig
{{ video.getMuxVideo()|attr({
    autoplay: 'muted',
    loop: true,
    muted: true,
    playsinline: true,
    disablePictureInPicture: true
}) }}
```

#### Object-fitted Mux videos

Styling `<mux-video>` is generally straight-forward. One exception is object-fitting, since `object-fit` needs to be applied to the actual `<video>` element – which can't be accessed as it's nested in the web component's shadow DOM.   

By default, Mux videos are object-fitted using `object-fit: contain`. **To change the object-fit value, the following CSS vars can be used:**  

```
--media-object-fit
--media-object-position
```

Example using `object-fit: cover;`:  

```twig
{{ video.getMuxVideo({ inline: true })|attr({
    style: {
        '--media-object-fit': 'cover',
        '--media-object-position': '25% 10%'
    }
}) }}
```

#### Control Mux video playback with JS

Generally, `<mux-video>` works the same as native `<video>` elements – i.e. it has same methods (`.play()`, `.pause()`, etc), and emits the same events (`timeupdate` etc).   

However, before attempting to interact with a Mux video programmatically, you should make sure that the custom `<mux-video>` web component has loaded, using [the `customElements` API](https://developer.mozilla.org/en-US/docs/Web/API/Window/customElements):   

```js
window.customElements.whenDefined('mux-video')
    .then(() => {
        const video = document.getElementById('video');
        video.addEventListener('canplay', () => {
            video.play();
            console.log('ready!');
        });
    })
    .catch(error => {
        console.error(error);
    });
```

#### Lazy-loading the `<mux-video>` web component

The `<mux-video>` web component is a little bit beefy (~150K gzipped), so it can make sense to lazy-load it – i.e. not load it before a `<mux-video>` element actually enters the viewport.  

To automatically lazy-load the `<mux-video>` web component everywhere, simply set the `lazyloadMuxVideo` config setting to `true`:  

```php
<?php

return [
    'lazyloadMuxVideo' => true,
];
```  

##### Implementing your own lazy loading strategy

For more advanced use cases, you can prevent MuxMate from automatically loading the `<mux-video>` web component at all, by setting the `muxVideoUrl` config setting to `false`:  

```php
<?php

return [
    'muxVideoUrl' => false,
];
```

At that point, you're free to load the web component yourself in whatever lazy fashion you fancy. For example:   

```js
const video = document.getElementById('video');
let hasLoadedMuxVideoJs = false;
const observer = new IntersectionObserver(([{ isIntersecting }]) => {
    if (isIntersecting && !hasLoadedMuxVideoJs) {
        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.async = true;
        script.src = 'https://cdn.jsdelivr.net/npm/@mux/mux-video@0';
        document.body.appendChild(script);
    }
});
```

##### Content-Security Policy (CSP)  

If you're implementing a CSP (good idea!), you might need to set a nonce to the script tags created by MuxMate. To do that, use the `scriptSrcNonce` config setting. Here's an example using the ToolMate plugin to create the nonce:  

```php
<?php

use \vaersaagod\toolmate\ToolMate;

return [
    'scriptSrcNonce' => ToolMate::getInstance()->csp->createNonce('script-src'),
];
```  

This feature works even with template caching enabled 🔥

## Get images and animated GIFs from videos  

### Get image from video

The `getMuxImageUrl()` asset method supports all the same parameters as described in the Mux documentation: [https://docs.mux.com/guides/video/get-images-from-a-video](https://docs.mux.com/guides/video/get-images-from-a-video).  

Example:  

```twig
{% set imageUrl = video.getMuxImageUrl({ width: 1080, height: 720 }) %}
<img src="{{ imageUrl }}" />
```

### Get animated GIF from video

The `getMuxGifUrl()` asset method supports all the same parameters as described in the Mux documentation: [https://docs.mux.com/guides/video/get-images-from-a-video#get-an-animated-gif-from-a-video](https://docs.mux.com/guides/video/get-images-from-a-video#get-an-animated-gif-from-a-video).

Example:

```twig
{% set gifUrl = video.getMuxGifUrl({ start: 10, end: 20, width: 300, height: 150 }) %}
<img src="{{ gifUrl }}" />
```

## Signing URLs

In order to protect your Mux assets, [signing the Mux URLs](https://www.mux.com/blog/securing-video-content-with-signed-urls) can be a good idea.  

### Create a signing key

The first thing you'll need to do in order to start working with Mux signed URLs, is creating a Mux Signing Key:  

1. In your Mux account, navigate to Settings -> Signing Keys
2. Select the proper environment, then click "Generate new key"  
3. Copy the Signing Key ID and Base64-encoded Private Key (do not download as a .pem file)
4. Configure MuxMate to use the signing key:  

```php
<?php
return [
    ...
    'muxSigningKey' => [
        'id' => App::env('MUX_SIGNING_KEY_ID'),
        'privateKey' => App::env('MUX_SIGNING_PRIVATE_KEY'),
    ],
],
```

### Selecting a playback policy

MuxMate creates public and signed playback IDs for all your assets, so using the signed ID is simply a matter of selecting the proper policy.  

By default, MuxMate uses the *public* playback IDs for all assets. To use the *signed* playback IDs by default, configure MuxMate's `defaultPolicy` config setting:  

```php
<?php
return [
    ...
    'defaultPolicy' => 'signed',
];
```

It's also possible to select a specific policy whenever you create a video stream, a URL to a static rendition or an image/GIF, by passing a parameter `signed`.  
Some examples:  

#### Signing a Mux image URL

```twig
{% set imageUrl = video.getMuxImageUrl({ width: 1080, height: 720 }, 'signed') %}
<img src="{{ imageUrl }}" />
```

#### Signing a Mux GIF url

```twig
{% set gifUrl = video.getMuxGifUrl({ start: 10, end: 20, width: 300, height: 150 }, 'signed') %}
<img src="{{ gifUrl }}" />
```

#### Signing a `<mux-video>` tag

```twig
{{ video.getMuxVideo({ inline: true }, null, 'signed') }}
```

See [Mux asset methods and attributes](#mux-asset-methods-and-attributes) for a complete overview of the methods that support the `signed` parameter.  

## Querying for assets based on Mux data  

It's possible to execute Asset sub queries based on Mux metadata by using the MuxMate field's handle, for example:   

```twig
{% set videos = entry.videos.muxMateFieldHandle({ status: 'ready' }).all() %}
```

Additionally, the `:empty:` and `:notempty:` directives are supported and can be ensured that assets returned has Mux data in the MuxMate field:  

```twig
{% set videos = entry.videos.muxMateFieldHandle(':notempty:').all() %}
```

## Configuration

MuxMate is configured by adding a file `config/_muxmate.php` to your repository.  

### Config settings

#### `muxAccessTokenId` [string|null]  
Default: `null`  

The Mux access token ID  

#### `muxSecretKey` [string|null]  
Default: `null`  

The Mux access token secret key  

#### `muxSigningKey` [array|null]  
Default `null`  

The signing key ID and private key to use for signing URLs.

```php
'muxSigningKey' => [
    'id' => App::env('MUX_SIGNING_KEY_ID'),
    'privateKey' => App::env('MUX_SIGNING_PRIVATE_KEY'),
    'minExpirationTime' => 'PT5M',
],
```

#### `defaultPolicy` [string|null]  
Default `null` (defaults to `'public'`)    

The default playback policy to use when generating Mux URLs. Should be set to `'public'` (default) or `'signed'`.  

#### `defaultMp4Quality` [string|null]  
Default `null` (defaults to `'high'`)  

The default quality to use for static renditions. Needs to be one of `'high'` (default), `'medium'` or `'low'`.  

#### `defaultMaxResolution` [string|null]  
Default `null`  

The default [`max_resolution`](https://docs.mux.com/guides/video/control-playback-resolution#specify-maximum-resolution) param to use for HLS streams. Needs to be one of `'720p'`, `'1080p'`, `'1440p'` or `'2160p'`.      

#### `maxResolutionTier` [string|null]  
Default `null` (defaults to `'1080p'`)  

The default `max_resolution_tier` param to use when creating new Mux assets. Needs to be one of `'1080p'`, `'1440p'` or `'2160p'`.  

#### `muxVideoUrl` [string|bool|null]
Default: `'https://cdn.jsdelivr.net/npm/@mux/mux-video@0'`  

The URL to the `<mux-video>` JS library. Set it to a different URL (aliases and environment variables are supported) if you want to use a different distribution, or set it to `false` to handle loading the library completely yourself (i.e. for custom lazy loading purposes or the like).  

#### `lazyloadMuxVideo` [bool]  
Default: `false`  

Set to `true` to make MuxMate lazy load the `<mux-video>` library. I.e. instead of automatically being loaded at pageload (albeit async), MuxMate will create an IntersectionObserver and load the script as soon as a `<mux-video>` component enters the viewport.  

#### `scriptSrcNonce` [string|null]  
Default `null`  

#### `volumes` [array|null]
Default: `null`
Per-volume config settings. Currently only supports a `baseUrl` setting, see "Override your volume's file system Base URL in local environments" above.  

## Mux asset methods and attributes
MuxMate adds some methods and attributes to asset models:

### `isMuxVideo()` [bool]  
Returns `true` if the asset has a Mux asset ID.  

### `isMuxVideoReady()` [bool]  
Returns `true` if the asset has a Mux asset ID and a "ready" status, i.e. is ready to play.  

### `getMuxVideo(array|null options = null, array|null params = null, string|null policy = null)` [Markup|string]  
`@options` Array of options; `inline`, `lazyload`    
`@params` Array of Mux parameters  
`@policy` One of `'public'`, `'signed'`  

If the asset has a Mux playback ID, returns a `<mux-video>` web component.  

The `options` array can contain the following settings:  

```twig
inline: true # Automatically sets all the required attributes for videos that should play inline.
lazyload: true # Lazyloads the `<mux-video>` web component. Defaults to the `lazyloadMuxVideo` config setting
```

The `params` array can contain Mux video params (i.e. "playback modifier"), such as the `max_resolution` param:  

```twig
{{ asset.getMuxVideo(null, { max_resolution: '720p' }) }}
```

### `getMuxStreamUrl(array|null params = null, string|null policy = null)` [string|null]
`@params` Array of Mux parameters  
`@policy` One of `'public'`, `'signed'`    

If the asset has a Mux playback ID, returns a HLS stream URL. The `@params` array is the same as for the `getMuxVideo()` method.    

### `getMuxMp4Url(string|null quality = null, string|null policy = null)` [string|null]  
`@quality` Oone of `'high'`, `'medium'`, `'low'`     
`@policy` One of `'public'`, `'signed'`  

If the asset has a Mux playback ID and static renditions, returns the URL to an MP4 file.  
If the `quality` param is null or set to a quality that isn't available, the highest available quality is returned.  

### `getMuxImageUrl(array|null params = null, string|null policy)` [string|null]  
`@params` Array of Mux parameters, see https://docs.mux.com/guides/video/get-images-from-a-video#thumbnail-query-string-parameters  
`@policy` One of `'public'`, `'signed'`  

If the asset has a Mux playback ID, returns the URL to a still frame from the video.    

### `getMuxGifUrl(array|null params = null, string|null policy)` [string|null]
`@params array` Array of parameters, see https://docs.mux.com/guides/video/get-images-from-a-video#animated-gif-query-string-parameters  
`@policy` One of `'public'`, `'signed'`  

If the asset has a Mux playback ID, returns an animated GIF from the video.

### `getMuxVideoDuration()` [float|null]  
Returns the video duration, in seconds  

### `getStaticRenditions()` [array|null]  
Returns an array of the available static renditions for the video. The array is indexed by quality (`'high'`, `'medium'` and `'low'`).  

### `getMuxAssetId()` [string|null]  
Returns the Mux asset ID.

### `getMuxPlaybackId(string|null policy)` [string|null]  
`@policy` One of `'public'`, `'signed'`  
Returns a Mux playback ID for the given policy.  

### `getMuxData()` [array|null]  
Returns the entire Mux asset metadata payload. See https://docs.mux.com/api-reference#video/operation/get-asset

### `getMuxStatus()` [string|null]  
Returns the Mux asset status. If it says `ready`, you're good to go.  

### `getMuxAspectRatio()` [float|int|null]  
Returns the aspect ratio for the video


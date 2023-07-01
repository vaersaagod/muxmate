# MuxMate

Mux ado about streaming, mate!

## Description  

MuxMate integrates [Mux](https://www.mux.com/) with Craft CMS.    

## Requirements

This plugin requires Craft CMS 4.4.0 or later, and PHP 8.0.2 or later.

## Getting started  

### 1. Create a Mux environment

Register a Mux account on https://www.mux.com, and add an environment. _It's a good idea to have separate Mux environments for each Craft environment._   

### 2. Create a Mux access token

Create a Mux access token for the appropriate Mux environment. The token only needs the "Mux Video" permission.    
Copy the access token ID and secret key.  

### 3. Add the MuxMate webhook endpoint to the Mux environment

In your Mux account, visit Settings -> Webhooks, select the appropriate environment, and create a new webhook with this URL:  

`https://your-site.com/muxmate/webhook`  

_If this is a local environment (or otherwise not accessible from outside), you can use Ngrok or similar to create a publicly available Webhook endpoint URL._

### 4. Configure MuxMate  

Create a file `config/_muxmate.php` in your repository, and add the Mux access token ID and secret key:  

```php
<?php

use craft\helpers\App;

return [
    'muxAccessTokenId' => App::env('MUX_ACCESS_TOKEN_ID'),
    'muxSecretKey' => App::env('MUX_SECRET_KEY'),
];
```

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

Generally, `<mux-video>` works the same as native `<video>` elements – i.e. it has same methods (`.play()`, .pause()`, etc), and emits the same events (`timeupdate` etc).   

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

The `<mux-video>` web component is a little bit beefy (~150K gzipped), so it can make sense to lazy-load it. There are a few ways to do that:  

##### Lazy-loading `<mux-video>` everywhere all the time

To automatically lazy-load the `<mux-video>` web component everywhere, simply set the `lazyloadMuxVideo` config setting to `true`. Done.

##### Lazy-loading per `<mux-video>` instance  

Alternatively, you can tell MuxMate to lazy load the `<mux-video>` web component by passing `lazyload: true` to the `getMuxVideo()` method:  

```twig
{{ asset.getMuxVideo({ lazyload: true }) }}
```

##### Implementing your own lazy loading strategy

For more advanced use cases, you can prevent MuxMate from automatically loading the `<mux-video>` web component at all, by setting the `muxVideoUrl` config setting to `false`:  

```php
<?php

return [
    'muxVideoUrl' => false,
];
```

Then, you're free to load the web component yourself, however you like, in whatever lazy fashion you fancy. For example:   

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

If you're implementing a CSP, you might need to set a nonce to the script tags created by MuxMate. To do that, use the `scriptSrcNonce` config setting. Here's an example using the ToolMate plugin to create the nonce:  

```php



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

## Configuration

MuxMate is configured by adding a file `config/_muxmate.php` to your repository.  

### Config settings

#### `muxAccessTokenId` [string|null]  
Default: `null`  

The Mux access token ID  

#### `muxSecretKey` [string|null]  
Default: `null`  

The Mux access token secret key  

#### `muxVideoUrl` [string|bool|null]
Default: `'https://cdn.jsdelivr.net/npm/@mux/mux-video@0'`  

The URL to the `<mux-video>` JS library. Set it to a different URL (aliases and environment variables are supported) if you want to use a different distribution, or set it to `false` to handle loading the library completely yourself (i.e. for custom lazy loading purposes or the like).  

#### `lazyloadMuxVideo` [bool]  
Default: `false`  

Set to `true` to make MuxMate lazy load the `<mux-video>` library. I.e. instead of automatically being loaded at pageload (albeit async), MuxMate will create an IntersectionObserver and load the script as soon as a `<mux-video>` component enters the viewport.  

#### `scriptSrcNonce` [string|null]  

If you're implementing a Content-Security Policy (good idea!), you might need to set a nonce for the script tag(s) that MuxMate injects to the page. Here's an example using the ToolMate plugin:  

```php
<?php

use \vaersaagod\toolmate\ToolMate;

return [
    'scriptSrcNonce' => ToolMate::getInstance()->csp->createNonce('script-src'),
];
```

#### `volumes` [array|null]
Default: `null`
Per-volume config settings. Currently only supports a `baseUrl` setting, see "Override your volume's file system Base URL in local environments" above.  

## Mux asset methods and attributes
MuxMate adds some methods and attributes to asset models:

### `isMuxVideo()` [bool]  
Returns `true` if the asset has a Mux playback ID.  

### `isMuxVideoReady()` [bool]  
Returns `true` if the asset has a Mux playback ID and a "ready" status, i.e. is ready to play.  

### `getMuxVideo()` [Markup|null]
`@config` bool [default `false`] Automatically sets all the required attributes for videos that should play inline  
`@lazyload` bool [default `false`] 
If the asset has a Mux playback ID, returns a `<mux-video>` web component.  

### `getMuxStreamUrl()` [string|null]
If the asset has a Mux playback ID, returns the HLS stream URL.

### `getMuxMp4Url(string quality = null)` [string|null]  
`@quality string [one of "high", "medium", "low"]`  

If the asset has a Mux playback ID and static renditions, returns the URL to an MP4 file.  
If the `quality` param is null or set to a quality that isn't available, the highest available quality is returned.  

### `getMuxImageUrl(array params = [])` [string|null]  
@params Array of parameters, see https://docs.mux.com/guides/video/get-images-from-a-video#thumbnail-query-string-parameters

If the asset has a Mux playback ID, returns the URL to a still frame from the video.    

### `getMuxGifUrl(array params = [])` [string|null]
`@params array` Array of parameters, see https://docs.mux.com/guides/video/get-images-from-a-video#animated-gif-query-string-parameters

If the asset has a Mux playback ID, returns an animated GIF from the video.

### `getMuxAssetId()` [string|null]  
Returns the Mux asset ID.

### `getMuxPlaybackId()` [string|null]  
Returns the Mux playback ID.

### `getMuxData()` [array|null]  
Returns the entire Mux asset metadata payload. See https://docs.mux.com/api-reference#video/operation/get-asset

### `getMuxStatus()` [string|null]  
Returns the Mux asset status. If it says `ready`, you're good to go.  

### `getMuxAspectRatio()` [int|null]  
Returns the aspect ratio for the video  

### `getStaticRenditions()` [array|null]  
Returns an array of the static renditions (i.e. MP4), indexed by their quality (`'high'`, `'medium'` or `'low'`)

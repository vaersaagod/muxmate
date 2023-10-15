# MuxMate Changelog

## 2.0.0 - 2023-10-15
### Added
- Added support for signing URLs  
- Added support for Mux' `max_resolution` param for the `Asset::getMuxStreamUrl()` and `Asset::getMuxVideo()` methods
- Added the `maxResolution` config setting for setting a default max resolution   
- Added the `muxSigningKey` config setting for setting a signing key used in signing URLs
- Added the `defaultPolicy` config setting for setting the default playback policy ("signed" or "public"; public is the default)  
- Added the `_muxmate/create` command for creating Mux assets from existing Craft assets in bulk 
- Added the `_muxmate/create/playback-ids` command for creating new (or missing) Mux playback IDs for existing Mux assets in bulk
- Added the ability to query for assets based on Mux meta data as JSON queries, e.g. `entry.videos.mux({ status: 'ready' }).all()`
### Changed
- Removed the `muxPlaybackId` content table column 

## 1.2.0 - 2023-07-01  
### Added
- Added the ability to have MuxMate automatically lazyload the `<mux-video>` web component
- Added the ability to have MuxMate *not* load the `<mux-video>` web component JS library at all
- Added the `lazyloadMuxVideo` config setting  
- Added the `scriptSrcNonce` config setting  
### Changed
- The `muxVideoUrl` config setting now supports a `false` value, in which case MuxMate will not load the `<mux-video>` library
- The `Asset::getMuxVideo()` method no longer returns `null`, avoiding a PHP exception that would occur if using the `|attr()` filter directly on its output.
### Improved  
- Improved video previews when assets are missing their Mux playback ID  

## 1.1.3 - 2023-06-23  
### Fixed
- Fixed dumb bug

## 1.1.2 - 2023-06-21
### Fixed 
- Fixed an issue where not all params passed to `getMuxImageUrl()` would make it 

## 1.1.1 - 2023-06-21
### Fixed  
- Fixed a PHP exception due to a typing error

## 1.1.0 - 2023-06-21
### Added  
- Added the `isMuxVideo()` and `isMuxVideoReady()` asset methods.  

## 1.0.3 - 2023-06-21
### Fixed
- Fixed an issue where the `getMuxAspectRatio()` always returned `1`

## 1.0.2 - 2023-06-13
### Improved
- More emoji statuses in MuxMate field table attributes 🎉
### Changed
- MuxMate now requires PHP 8.1+
- MuxMate fields now only uses the Mux asset ID for the search index

## 1.0.1 - 2023-06-13
### Fixed
- Fixed some visual bugs in the MuxMate input field template

## 1.0.0 - 2023-06-13
### Added
- Initial release


=== Storyform ===

Contributors: RylanH
Donate link: http://storyform.co
Tags: storyform, narrative, magazine, longform, photography, slider
Requires at least: 2.6.1
Tested up to: 4.0.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-0.3.html

The Storyform plugin let's you select which posts you want to create Storyform magazines with.

== Description ==

The Storyform plugin let's you selectively choose posts to apply the [Storyform](http://storyform.co) platform to. Easily set your application key and choose which Storyform templates to use on each post.

With the Storyform platform you can turn your longform WordPress posts into a well-designed magazine-style article. The Storyform platform supports print-like features such as:

- a diverse set of well-crafted layouts
- touch, mouse and keyboard friendly pagination, no more scrolling
- multi-column articles
- high quality typography
- drop-caps
- pullquotes
- large photography
- animations
- rich video

= Docs, Support and Resources =

Visit the [Official Storyform Plugin Homepage](http://storyform.co/docs/wordpress) for more detailed information or [Storyform](http://storyform.co) to hear more about the platform.

= Using Storyform =

Storyform is built to work with mainly HTML5 standard semantic article content. It attempts to remove any inline styling or extraneous elements and simply uses the underlying semantic elements. It then applies it's template layout and styling that you chose for the post. Storyform does not support:

- `<object>` based videos or content
- `<iframe>` embedded elements other than YouTube videos
- links or alignment attributes on embedded media


== Installation ==

Use the built-in wordpress admin installation mechanism (recommended):

1. Go to your Wordpress admin page, choose 'Plugins' in the menu.
1. Click 'Add New', search for 'Storyform', click 'Install Now'.
1. Activate the plugin.
1. Go to the 'Settings' > 'Wordpress settings' page in your admin portal and enter your [application key](http://storyform.co).
1. Go to the desired post's edit page and find the Storyform meta box to set the appropriate Storyform templates to use.

OR manually install the plugin:

1. Upload the entire storyform folder to the /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Go to the 'Settings' > 'Wordpress settings' page in your admin portal and enter your [application key](http://storyform.co).
1. Go to the desired post's edit page and find the Storyform meta box to set the appropriate Storyform templates to use.

== Frequently Asked Questions ==

= Can I get an application key? =

Storyform is only just getting started. We can't quite handle everyone yet and we want to make sure you have a great experience when we can. Until then, feel free to [request an app key](http://storyform.co) and we will get back to you once we are ready.

= When I preview my page it shows up blank. What's going on? =

Storyform is still a bit fragile. We are working out the kinks. Start by going to your post's content and ensuring that there aren't any funny elements (`<object>` tags, `<iframe>`s or other interesting elements). Remove them one by one and see if that helps. If not, please contact us at hello at storyform.co

== Screenshots ==

1. Bold and beautiful templates.
1. Easily add pull quotes anywhere in your content.
1. Rich edge to edge images.

== Changelog ==

= 0.3.8 =

* Rebrand of Narrative to Storyform
* Option to insert featured image into the article as a cover image.
* VIP uses post_meta instead of option.
* Option to add some new large image sizes for responsive images.
* Removal of fallback to old theme when on an unsupported browser.

== Upgrade Notice ==

= 0.3.8 =

* Rebrand of Narrative to Storyform
* Option to insert featured image into the article as a cover image.
* Option to add some new large image sizes for responsive images.
* Removal of fallback to old theme when on an unsupported browser.

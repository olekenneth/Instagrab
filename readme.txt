=== Instagrab ===
Contributors: olekenneth
Donate link: http://instagrab.org/donate
Tags: instagram, hashtag, photo, image, download, grab, share, cache
Requires at least: 2.7
Tested up to: 3.4.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Instagrab is a Wordpress plugin that grabs images from one or more Instagram hashtags and create a post for each image.

== Description ==

Instagrab is a Wordpress plugin that grabs images from one or more Instagram hashtags and create a post for each image.

**BIG UPDATE**
Fixed issues when following hashtags with high post pace. This update is crucial for everyone. Please update.

**What does Instagrab do for me?**

It let you follow one or more Instagram hashtags and automatically creates a new blog post for each photo it finds.

**What do I use Instagrab for?**

It’s purpose was initially to be a photo contests plugin, but I see a number of other uses, like following pictures taken that is tagged with your brand, favorite artist e.g. Or you can just create your own hashtag and post all your photos automagicly to your own blog.

**Is it hard to start using?**

Like most WordPress plugins, no.  Just search for Instagrab at Add new plugin inside the admin panel of your blog. Or you can download it here: http://wordpress.org/extend/plugins/instagrab/ and upload it to `wp-content/plugins`.

**Does it work on WordPress.com-blogs?**

Sorry, no. The WP.com-site only support their own plugins.


== Installation ==

1. Upload `instagrab`-folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings -> `Instagrab`
4. Save hashtag(s) you want to follow, username/password for Instagram and the intervall for checks

== Frequently Asked Questions ==

= I have many hashtags, but the plugin only updates the last one =

Please don't spam the Instagram API with super many hashtags! Only use 1-5 hashtags, please. Your site is going to be slow and Instagram won't like it. 

= Why does the sites respond slower after I'd installed the plugin? =

Because you haven't set the timeout interval in the settings panel.
Set it to 30 sec or more.

= I use WP Super Cache e.g., why doesn't this plugin work? =

This will make a lot of problems for you. It will only download new pictures from Instagram when the cache has expired. Sorry! But you are more then welcome to try. If you know what you are doing, you can config the right stuff and you can get the result you want. 

== Screenshots ==

1. Settings panel.

== Changelog ==

= 1.0 =
* Fixed bug that occurred when following hashtags with high post pace.  
* Fixed bug with author-url


= 0.9 =
* Jumped version number to be prepared for 1.0
* Added Instagram logo and link to each post.

= 0.1.0 =
* Added caching table to ensure we are not getting the same images over and over.
* Removed anonymous function. Some users with old version of PHP had problems since it was not supported before 5.3.0.

= 0.0.1 =
* Added basic functionality.

== Upgrade Notice ==

= 1.0 =
* Important update! Just update, please.

= 0.9 = 
* Still nothing.

= 0.1.0 =
* Nothing special.

= 0.0.1 =
* Just install :-)
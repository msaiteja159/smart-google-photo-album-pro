=== HH CJ Google Photos (Smart Photo Gallery Pro) ===
Contributors: yourname
Tags: gallery, photo, images, ai, face detection, masonry, lightbox, google photos
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered photo management system with Google Photos integration, Pinterest-style masonry grid, face detection, auto-tagging, and advanced search capabilities.

== Description ==

Smart Photo Gallery Pro is an advanced WordPress plugin that transforms your website into a powerful, AI-powered photo management system similar to Google Photos. It provides a beautiful, customizable gallery interface with intelligent features powered by Google Vision API or AWS Rekognition.

= Key Features =

* **Pinterest-Style Masonry Grid Layout** - Beautiful, responsive gallery display with infinite scroll
* **AI-Powered Auto-Tagging** - Automatically detect objects, scenes, and content in photos
* **Face Detection & Grouping** - Automatically detect faces and create "People" albums
* **Advanced Search** - Search by keywords, tags, categories, and date ranges
* **Lightbox View** - Full-screen image viewing with download and share options
* **User Uploads** - Allow logged-in users to upload photos (with moderation)
* **Social Sharing** - Share photos on Facebook, Twitter, WhatsApp, and more
* **Category Albums** - Organize photos by Events, People, Travels, Birthdays, etc.
* **Responsive Design** - Mobile-first design that works on all devices
* **Dark Mode Support** - Optional dark theme for better viewing experience

= AI Features =

* Face detection and person grouping
* Automatic label/tag generation from photo content
* Smart album suggestions based on content analysis
* Related photos using similarity matching

= User Features =

* Upload photos with title, description, tags, and categories
* Select event dates and locations
* View personal gallery ("My Gallery")
* Like and favorite photos
* Download high-resolution images

= Admin Features =

* Complete control panel under "Photo Gallery" menu
* Configure Google Vision API or AWS Rekognition
* Moderate user uploads (approve/reject)
* View and manage detected people
* Customize gallery layout and behavior
* Enable/disable features individually

= Shortcodes =

* `[smart_gallery]` - Display the full photo gallery
* `[smart_upload]` - Show upload form for logged-in users
* `[smart_search]` - Display search bar and results

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Google Vision API key OR AWS Rekognition credentials (for AI features)
* jQuery (included with WordPress)

== Installation ==

1. Upload the `smart-photo-gallery-pro` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to "Photo Gallery > Settings" to configure API keys
4. Add the shortcode `[smart_gallery]` to any page to display the gallery

= API Setup =

1. **Google Vision API:**
   - Go to Google Cloud Console
   - Enable Vision API
   - Create credentials (API Key)
   - Paste the key in plugin settings

2. **AWS Rekognition:**
   - Sign in to AWS Console
   - Create IAM user with Rekognition access
   - Get Access Key and Secret Key
   - Enter credentials in plugin settings

== Frequently Asked Questions ==

= Do I need an API key to use the plugin? =

The basic gallery features work without an API key. However, AI features (face detection, auto-tagging) require either Google Vision API or AWS Rekognition credentials.

= Can users upload photos without moderation? =

Yes, you can disable moderation in the settings. By default, user uploads require admin approval.

= Does the plugin support video files? =

Currently, the plugin only supports image files (JPEG, PNG, GIF, WebP).

= Can I customize the gallery layout? =

Yes, you can choose between Masonry Grid, Regular Grid, or Justified Grid layouts in the settings.

= How are faces grouped together? =

The plugin detects faces in photos. You can manually assign names to detected faces, and the plugin will group photos by person.

== Changelog ==

= 1.0.1 =
* Fixed invalid post type errors
* Improved Google Photos integration
* Added category-wise album display
* Enhanced single photo overlay view
* Changed name to "HH CJ Google Photos"
* Security improvements
* Better error handling
* Fixed Google Photos OAuth connection

= 1.0.0 =
* Initial release
* Pinterest-style masonry grid layout
* AI-powered face detection and auto-tagging
* Advanced search functionality
* User upload system
* Lightbox with social sharing
* Category-based albums
* Responsive design
* Dark mode support
* REST API endpoints
* Google Photos import integration

== Upgrade Notice ==

= 1.0.1 =
Major update with bug fixes, Google Photos improvements, and enhanced UI. Please flush rewrite rules after updating (Settings → Permalinks → Save).

== Screenshots ==

1. Pinterest-style masonry grid gallery
2. Lightbox view with photo details
3. User upload form
4. Advanced search with filters
5. Admin settings page
6. People management interface

== Support ==

For support, feature requests, or bug reports, please visit the plugin support forum.

== Credits ==

* Masonry.js by David DeSandro
* LightGallery by SachinN
* Google Vision API
* AWS Rekognition

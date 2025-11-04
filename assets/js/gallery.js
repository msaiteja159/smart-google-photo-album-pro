/**
 * Smart Photo Gallery Pro - Frontend JavaScript
 */

(function($) {
    'use strict';
    
    var SPGP = {
        init: function() {
            this.initMasonry();
            this.initLightbox();
            this.initSearch();
            this.initInfiniteScroll();
            this.initUpload();
            this.initLikesFavorites();
            this.initDarkMode();
        },
        
        /**
         * Initialize Masonry Grid
         */
        initMasonry: function() {
            var $gallery = $('.spgp-gallery-masonry');
            if ($gallery.length && typeof Masonry !== 'undefined') {
                var masonry = new Masonry($gallery[0], {
                    itemSelector: '.spgp-gallery-item',
                    columnWidth: '.spgp-gallery-item',
                    percentPosition: true,
                    transitionDuration: 0
                });
                
                // Relayout on images loaded
                $gallery.imagesLoaded(function() {
                    masonry.layout();
                });
            }
        },
        
        /**
         * Initialize Lightbox
         */
        initLightbox: function() {
            if (typeof lightGallery !== 'undefined') {
                $('.spgp-gallery-masonry, .spgp-gallery-grid').on('click', '.spgp-gallery-item', function(e) {
                    e.preventDefault();
                    
                    var $item = $(this);
                    var photoId = $item.data('photo-id');
                    var photoUrl = $item.data('photo-url') || $item.find('img').attr('src');
                    var photoTitle = $item.data('photo-title') || $item.find('.spgp-gallery-item-title').text();
                    var photoDescription = $item.data('photo-description') || '';
                    
                    // Get photo details via AJAX
                    $.ajax({
                        url: spgpData.ajaxUrl,
                        type: 'GET',
                        data: {
                            action: 'spgp_get_photo_details',
                            photo_id: photoId
                        },
                        success: function(response) {
                            if (response.success) {
                                SPGP.openLightbox(response.data);
                            }
                        },
                        error: function() {
                            // Fallback to simple lightbox
                            SPGP.openSimpleLightbox(photoUrl, photoTitle);
                        }
                    });
                });
            }
        },
        
        /**
         * Open Lightbox with full details
         */
        openLightbox: function(photo) {
            var shareUrl = window.location.origin + photo.url;
            var shareTitle = photo.title;
            
            var html = '<div class="spgp-lightbox-content">';
            html += '<img src="' + photo.image + '" alt="' + photo.title + '" />';
            html += '<div class="spgp-lightbox-info">';
            html += '<h2 class="spgp-lightbox-title">' + photo.title + '</h2>';
            if (photo.description) {
                html += '<p class="spgp-lightbox-description">' + photo.description + '</p>';
            }
            html += '<div class="spgp-lightbox-meta">';
            if (photo.event_date) {
                html += '<span>Date: ' + photo.event_date + '</span>';
            }
            if (photo.location) {
                html += '<span>Location: ' + photo.location + '</span>';
            }
            html += '</div>';
            html += '<div class="spgp-lightbox-actions">';
            
            // Download button
            html += '<a href="' + photo.image + '" download class="spgp-lightbox-button spgp-download-btn">';
            html += '<span>⬇</span> ' + spgpData.strings.download;
            html += '</a>';
            
            // Share buttons
            html += '<a href="' + SPGP.getShareUrl('facebook', shareUrl, shareTitle) + '" target="_blank" class="spgp-lightbox-button">Facebook</a>';
            html += '<a href="' + SPGP.getShareUrl('twitter', shareUrl, shareTitle) + '" target="_blank" class="spgp-lightbox-button">Twitter</a>';
            html += '<a href="' + SPGP.getShareUrl('whatsapp', shareUrl, shareTitle) + '" target="_blank" class="spgp-lightbox-button">WhatsApp</a>';
            
            // Copy link
            html += '<button class="spgp-lightbox-button spgp-copy-link-btn" data-url="' + shareUrl + '">Copy Link</button>';
            
            // Like button
            if (spgpData.enableLikes) {
                html += '<button class="spgp-lightbox-button spgp-like-btn" data-photo-id="' + photo.id + '">';
                html += '<span class="spgp-like-icon">' + (photo.is_liked ? '❤️' : '🤍') + '</span> ';
                html += '<span class="spgp-like-count">' + photo.likes + '</span>';
                html += '</button>';
            }
            
            // Favorite button
            if (spgpData.enableFavorites) {
                html += '<button class="spgp-lightbox-button spgp-favorite-btn" data-photo-id="' + photo.id + '">';
                html += '<span class="spgp-favorite-icon">' + (photo.is_favorited ? '⭐' : '☆') + '</span>';
                html += '</button>';
            }
            
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Use lightGallery if available
            if (typeof lightGallery !== 'undefined') {
                lightGallery(document.body, {
                    dynamic: true,
                    dynamicEl: [{
                        src: photo.image,
                        html: html
                    }],
                    download: false,
                    plugins: ['thumbnail']
                });
            } else {
                // Fallback modal
                SPGP.showModal(html);
            }
        },
        
        /**
         * Open simple lightbox (fallback)
         */
        openSimpleLightbox: function(photoUrl, photoTitle) {
            var html = '<div class="spgp-lightbox-content">';
            html += '<img src="' + photoUrl + '" alt="' + photoTitle + '" />';
            html += '</div>';
            SPGP.showModal(html);
        },
        
        /**
         * Show modal
         */
        showModal: function(content) {
            var $modal = $('<div class="spgp-modal"><div class="spgp-modal-content">' + content + '</div><span class="spgp-modal-close">&times;</span></div>');
            $('body').append($modal);
            $modal.fadeIn();
            
            $modal.on('click', function(e) {
                if ($(e.target).is('.spgp-modal, .spgp-modal-close')) {
                    $modal.fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        },
        
        /**
         * Initialize Search
         */
        initSearch: function() {
            $('.spgp-search-form').on('submit', function(e) {
                e.preventDefault();
                SPGP.performSearch();
            });
            
            // Auto-search on input change (debounced)
            var searchTimeout;
            $('.spgp-search-input').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    SPGP.performSearch();
                }, 500);
            });
        },
        
        /**
         * Perform Search
         */
        performSearch: function() {
            var $form = $('.spgp-search-form');
            var $gallery = $('.spgp-gallery-masonry, .spgp-gallery-grid');
            var $loading = $('<div class="spgp-loading">' + spgpData.strings.loading + '</div>');
            
            $gallery.before($loading);
            $gallery.empty();
            
            $.ajax({
                url: spgpData.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'spgp_search_photos',
                    q: $form.find('.spgp-search-input').val(),
                    category: $form.find('.spgp-category-select').val() || 0,
                    date_from: $form.find('.spgp-date-from').val() || '',
                    date_to: $form.find('.spgp-date-to').val() || '',
                    tags: $form.find('.spgp-tags-input').val() || ''
                },
                success: function(response) {
                    $loading.remove();
                    if (response.success && response.data.photos.length > 0) {
                        SPGP.renderPhotos(response.data.photos);
                        SPGP.initMasonry();
                        SPGP.initLightbox();
                    } else {
                        $gallery.after('<div class="spgp-empty-state"><div class="spgp-empty-state-icon">📷</div><div class="spgp-empty-state-message">No photos found</div></div>');
                    }
                },
                error: function() {
                    $loading.remove();
                    $gallery.after('<div class="spgp-empty-state"><div class="spgp-empty-state-message">Error loading photos</div></div>');
                }
            });
        },
        
        /**
         * Render Photos
         */
        renderPhotos: function(photos) {
            var $gallery = $('.spgp-gallery-masonry, .spgp-gallery-grid');
            var html = '';
            
            $.each(photos, function(index, photo) {
                html += '<div class="spgp-gallery-item" ';
                html += 'data-photo-id="' + photo.id + '" ';
                html += 'data-photo-url="' + photo.url + '" ';
                html += 'data-photo-title="' + photo.title + '">';
                html += '<img src="' + photo.thumbnail + '" alt="' + photo.title + '" loading="lazy" />';
                html += '<div class="spgp-gallery-item-overlay">';
                html += '<div class="spgp-gallery-item-title">' + photo.title + '</div>';
                html += '<div class="spgp-gallery-item-meta">';
                if (photo.event_date) {
                    html += '<span>' + photo.event_date + '</span>';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });
            
            $gallery.append(html);
        },
        
        /**
         * Initialize Infinite Scroll
         */
        initInfiniteScroll: function() {
            if (!spgpData.infiniteScroll) {
                return;
            }
            
            var page = 1;
            var loading = false;
            var hasMore = true;
            
            $(window).on('scroll', function() {
                if (loading || !hasMore) {
                    return;
                }
                
                if ($(window).scrollTop() + $(window).height() >= $(document).height() - 500) {
                    loading = true;
                    page++;
                    
                    var $loading = $('<div class="spgp-loading">' + spgpData.strings.loading + '</div>');
                    $('.spgp-gallery-masonry, .spgp-gallery-grid').after($loading);
                    
                    $.ajax({
                        url: spgpData.ajaxUrl,
                        type: 'GET',
                        data: {
                            action: 'spgp_search_photos',
                            page: page
                        },
                        success: function(response) {
                            $loading.remove();
                            if (response.success && response.data.photos.length > 0) {
                                SPGP.renderPhotos(response.data.photos);
                                SPGP.initMasonry();
                                SPGP.initLightbox();
                                loading = false;
                            } else {
                                hasMore = false;
                                $('.spgp-gallery-masonry, .spgp-gallery-grid').after('<div class="spgp-load-more"><p>' + spgpData.strings.noMore + '</p></div>');
                            }
                        },
                        error: function() {
                            $loading.remove();
                            loading = false;
                        }
                    });
                }
            });
        },
        
        /**
         * Initialize Upload Form
         */
        initUpload: function() {
            $('.spgp-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submit = $form.find('.spgp-upload-submit');
                var formData = new FormData($form[0]);
                formData.append('action', 'spgp_upload_photo');
                formData.append('nonce', spgpData.uploadNonce);
                
                $submit.prop('disabled', true).text(spgpData.strings.loading);
                
                $.ajax({
                    url: spgpData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                $form[0].reset();
                                $('.spgp-upload-preview').empty();
                            }
                        } else {
                            alert(response.data.message);
                        }
                        $submit.prop('disabled', false).text('Upload Photo');
                    },
                    error: function() {
                        alert('Upload failed. Please try again.');
                        $submit.prop('disabled', false).text('Upload Photo');
                    }
                });
            });
            
            // Preview image
            $('.spgp-upload-form input[type="file"]').on('change', function(e) {
                var file = e.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('.spgp-upload-preview').html('<img src="' + e.target.result + '" />');
                    };
                    reader.readAsDataURL(file);
                }
            });
        },
        
        /**
         * Initialize Likes and Favorites
         */
        initLikesFavorites: function() {
            // Like button
            $(document).on('click', '.spgp-like-btn', function() {
                var $btn = $(this);
                var photoId = $btn.data('photo-id');
                
                $.ajax({
                    url: spgpData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'spgp_toggle_like',
                        photo_id: photoId,
                        nonce: spgpData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.find('.spgp-like-icon').text(response.data.liked ? '❤️' : '🤍');
                            $btn.find('.spgp-like-count').text(response.data.count);
                        }
                    }
                });
            });
            
            // Favorite button
            $(document).on('click', '.spgp-favorite-btn', function() {
                var $btn = $(this);
                var photoId = $btn.data('photo-id');
                
                $.ajax({
                    url: spgpData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'spgp_toggle_favorite',
                        photo_id: photoId,
                        nonce: spgpData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.find('.spgp-favorite-icon').text(response.data.favorited ? '⭐' : '☆');
                        }
                    }
                });
            });
            
            // Copy link button
            $(document).on('click', '.spgp-copy-link-btn', function() {
                var url = $(this).data('url');
                navigator.clipboard.writeText(url).then(function() {
                    alert('Link copied to clipboard!');
                });
            });
        },
        
        /**
         * Initialize Dark Mode
         */
        initDarkMode: function() {
            if (spgpData.darkMode || localStorage.getItem('spgpDarkMode') === 'true') {
                $('body').addClass('spgp-dark-mode');
            }
            
            $('.spgp-dark-mode-toggle').on('click', function() {
                $('body').toggleClass('spgp-dark-mode');
                localStorage.setItem('spgpDarkMode', $('body').hasClass('spgp-dark-mode'));
            });
        },
        
        /**
         * Get Share URL
         */
        getShareUrl: function(network, url, title) {
            var encodedUrl = encodeURIComponent(url);
            var encodedTitle = encodeURIComponent(title);
            
            var urls = {
                'facebook': 'https://www.facebook.com/sharer/sharer.php?u=' + encodedUrl,
                'twitter': 'https://twitter.com/intent/tweet?url=' + encodedUrl + '&text=' + encodedTitle,
                'whatsapp': 'https://wa.me/?text=' + encodedTitle + '%20' + encodedUrl,
                'linkedin': 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodedUrl,
                'pinterest': 'https://pinterest.com/pin/create/button/?url=' + encodedUrl + '&description=' + encodedTitle
            };
            
            return urls[network] || url;
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        SPGP.init();
    });
    
    // Reinitialize on AJAX content load
    $(document).ajaxComplete(function() {
        SPGP.initMasonry();
        SPGP.initLightbox();
    });
    
})(jQuery);

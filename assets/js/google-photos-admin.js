/**
 * Google Photos Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Disconnect button
        $('#spgp-disconnect-google').on('click', function() {
            if (confirm('Are you sure you want to disconnect from Google Photos?')) {
                $.ajax({
                    url: spgpGooglePhotos.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'spgp_disconnect_google_photos',
                        nonce: spgpGooglePhotos.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            }
        });
        
        // Sync albums
        $('#spgp-sync-google-albums').on('click', function() {
            var $button = $(this);
            $button.prop('disabled', true).text('Loading...');
            
            $.ajax({
                url: spgpGooglePhotos.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'spgp_google_photos_sync',
                    nonce: spgpGooglePhotos.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#spgp-albums-list').html(response.data.html);
                        alert(response.data.message);
                    } else {
                        alert(response.data.message || spgpGooglePhotos.strings.error);
                    }
                    $button.prop('disabled', false).text('Refresh Albums List');
                },
                error: function() {
                    alert(spgpGooglePhotos.strings.error);
                    $button.prop('disabled', false).text('Refresh Albums List');
                }
            });
        });
        
        // Import album
        $(document).on('click', '.spgp-import-album', function() {
            var $button = $(this);
            var albumId = $button.data('album-id');
            var albumTitle = $button.data('album-title');
            var $row = $button.closest('tr');
            var categoryId = $row.find('.spgp-category-select').val();
            
            if (!confirm('Import all photos from "' + albumTitle + '"?')) {
                return;
            }
            
            $button.prop('disabled', true).text(spgpGooglePhotos.strings.importing);
            
            $.ajax({
                url: spgpGooglePhotos.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'spgp_google_photos_import_album',
                    album_id: albumId,
                    album_title: albumTitle,
                    category_id: categoryId,
                    nonce: spgpGooglePhotos.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        $button.text('Imported');
                    } else {
                        alert(response.data.message || spgpGooglePhotos.strings.error);
                        $button.prop('disabled', false).text('Import');
                    }
                },
                error: function() {
                    alert(spgpGooglePhotos.strings.error);
                    $button.prop('disabled', false).text('Import');
                }
            });
        });
    });
    
})(jQuery);

/**
 * Well Web Notify — Admin JavaScript
 */
jQuery(function($) {
    'use strict';

    var i18n = (window.wellwebNotify && window.wellwebNotify.i18n) || {};

    // ─── Test channel button ──────────────────────────────────

    $(document).on('click', '.ww-notify-test-btn', function(e) {
        e.preventDefault();
        var $btn     = $(this);
        var channel  = $btn.data('channel');
        var $result  = $btn.siblings('.ww-notify-test-result');

        if (!$result.length) {
            $result = $('<span class="ww-notify-test-result"></span>');
            $btn.after($result);
        }

        $btn.addClass('--testing').prop('disabled', true);
        $result.removeClass('--success --error').text(i18n.testing || 'Sending...');

        $.ajax({
            url: wellwebNotify.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wellweb_notify_test',
                nonce: wellwebNotify.nonce,
                channel: channel
            },
            success: function(response) {
                if (response.success) {
                    $result.addClass('--success').text(i18n.success || 'Sent!');
                } else {
                    $result.addClass('--error').text(response.data || (i18n.error || 'Failed'));
                }
            },
            error: function() {
                $result.addClass('--error').text(i18n.error || 'Failed');
            },
            complete: function() {
                $btn.removeClass('--testing').prop('disabled', false);
                setTimeout(function() { $result.fadeOut(300, function() { $(this).text('').show(); }); }, 4000);
            }
        });
    });

    // ─── Highlight current tab ────────────────────────────────

    var href = window.location.href;
    var $menu = $('.ww-notify-menu');

    $menu.find('a').each(function() {
        if (href.indexOf($(this).attr('href')) !== -1) {
            $(this).closest('li').addClass('current');
        }
    });

    // If no tab matched, mark the first one
    if (!$menu.find('.current').length) {
        $menu.find('li').first().addClass('current');
    }

    // ─── Collapsible sections ─────────────────────────────────

    $(document).on('click', '.ww-collapsible .ww-settings-section-header', function(e) {
        // Don't toggle when clicking interactive elements
        if ($(e.target).is('input, label, button, a') || $(e.target).closest('input, label, button, a').length) {
            return;
        }
        $(this).closest('.ww-collapsible').toggleClass('ww-collapsed');
    });

    // Auto-expand channel section when toggled on
    $(document).on('change', '.ww-notify-toggle input', function() {
        var $section = $(this).closest('.ww-collapsible');
        if (this.checked) {
            $section.removeClass('ww-collapsed');
        }
    });

    // ─── Click to copy (webhook URL) ────────────────────────

    $(document).on('click', '.ww-copyable', function() {
        var $el   = $(this);
        var text  = $el.text().trim();
        var orig  = $el.text();

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                $el.text(i18n.copied || 'Copied!');
                setTimeout(function() { $el.text(orig); }, 1500);
            });
        } else {
            // Fallback for older browsers
            var $temp = $('<textarea>').val(text).appendTo('body').select();
            document.execCommand('copy');
            $temp.remove();
            $el.text(i18n.copied || 'Copied!');
            setTimeout(function() { $el.text(orig); }, 1500);
        }
    });

    // ─── Highlight Well Web parent menu ───────────────────────

    var $adminMenu = $('#adminmenu .toplevel_page_wellweb');
    $adminMenu
        .removeClass('wp-not-current-submenu')
        .addClass('wp-has-current-submenu wp-menu-open');

    // Highlight Notify submenu item
    $('#adminmenu').find('a[href*="wellweb-notify"]').first()
        .addClass('current')
        .closest('li').addClass('current');
});

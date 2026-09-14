(function () {
    'use strict';

    /*
     * =========================================================
     * pomfIB client-side features
     * =========================================================
     */


    /*
     * ---------------------------------------------------------
     * Inline image expansion
     * ---------------------------------------------------------
     *
     * Clicking a thumbnail expands it inside the post.
     * Clicking again shrinks it.
     */

    function toggleImage(image) {

        if (!image) {
            return;
        }


        if (
            image.classList.contains(
                'expanded-image'
            )
        ) {

            image.classList.remove(
                'expanded-image'
            );

            image.style.maxWidth = '';
            image.style.maxHeight = '';

            image.removeAttribute(
                'data-expanded'
            );

            return;
        }


        image.classList.add(
            'expanded-image'
        );

        image.setAttribute(
            'data-expanded',
            '1'
        );


        /*
         * Do not force a fixed width.
         * The browser will use the image's natural
         * dimensions while respecting the viewport.
         */

        image.style.maxWidth = '90vw';
        image.style.maxHeight = '90vh';
    }


    /*
     * Event delegation for post images.
     */

    document.addEventListener(
        'click',
        function (event) {

            var image =
                event.target.closest('.post-image');


            if (!image) {
                return;
            }


            event.preventDefault();
            event.stopPropagation();


            toggleImage(image);
        }
    );


    /*
     * ---------------------------------------------------------
     * Escape closes expanded images
     * ---------------------------------------------------------
     */

    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key !== 'Escape') {
                return;
            }


            var expanded =
                document.querySelectorAll(
                    '.post-image.expanded-image'
                );


            expanded.forEach(
                function (image) {

                    image.classList.remove(
                        'expanded-image'
                    );

                    image.style.maxWidth = '';
                    image.style.maxHeight = '';

                    image.removeAttribute(
                        'data-expanded'
                    );

                }
            );

        }
    );


    /*
     * ---------------------------------------------------------
     * Post highlighting
     * ---------------------------------------------------------
     */

    function highlightPost(post) {

        if (!post) {
            return;
        }


        document
            .querySelectorAll(
                '.post.highlighted-post'
            )
            .forEach(
                function (oldPost) {

                    oldPost.classList.remove(
                        'highlighted-post'
                    );

                }
            );


        post.classList.add(
            'highlighted-post'
        );


        window.setTimeout(
            function () {

                post.classList.remove(
                    'highlighted-post'
                );

            },
            2500
        );
    }


    /*
     * ---------------------------------------------------------
     * Find a post by canonical p123 ID
     * ---------------------------------------------------------
     */

    function getPostFromHash() {

        var hash =
            window.location.hash;


        if (!hash) {
            return null;
        }


        var id =
            hash.substring(1);


        if (!/^p[0-9]+$/.test(id)) {
            return null;
        }


        return document.getElementById(id);
    }


    /*
     * ---------------------------------------------------------
     * Highlight post when page loads with #p123
     * ---------------------------------------------------------
     */

    function handleInitialHash() {

        var post =
            getPostFromHash();


        if (!post) {
            return;
        }


        window.setTimeout(
            function () {

                post.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });


                highlightPost(post);

            },
            100
        );
    }


    /*
     * ---------------------------------------------------------
     * Browser back/forward hash navigation
     * ---------------------------------------------------------
     */

    window.addEventListener(
        'hashchange',
        function () {

            var post =
                getPostFromHash();


            if (!post) {
                return;
            }


            post.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });


            highlightPost(post);
        }
    );


    /*
     * ---------------------------------------------------------
     * Quick Reply Popup
     * ---------------------------------------------------------
     *
     * This works with the following board.php markup:
     *
     * .quick-reply-overlay
     * .quick-reply-popup
     * #quick-reply-form
     * #quick-reply-thread
     * #quick-reply-body
     * #quick-reply-close
     * #quick-reply-cancel
     *
     * Reply buttons use:
     *
     * class="reply-link"
     * data-thread-id="123"
     */


    var quickReplyOverlay = null;
    var quickReplyPopup = null;
    var quickReplyForm = null;
    var quickReplyThread = null;
    var quickReplyBody = null;
    var quickReplyClose = null;
    var quickReplyCancel = null;

    var previousActiveElement = null;


    /*
     * Find popup elements.
     */

    function initializeQuickReply() {

        quickReplyOverlay =
            document.getElementById(
                'quick-reply-overlay'
            );


        quickReplyPopup =
            document.getElementById(
                'quick-reply-popup'
            );


        quickReplyForm =
            document.getElementById(
                'quick-reply-form'
            );


        quickReplyThread =
            document.getElementById(
                'quick-reply-thread'
            );


        quickReplyBody =
            document.getElementById(
                'quick-reply-body'
            );


        quickReplyClose =
            document.getElementById(
                'quick-reply-close'
            );


        quickReplyCancel =
            document.getElementById(
                'quick-reply-cancel'
            );


        /*
         * This is important:
         *
         * pomfIB pages that do not contain a quick reply
         * popup should continue working normally.
         */

        if (
            !quickReplyOverlay ||
            !quickReplyPopup ||
            !quickReplyForm ||
            !quickReplyThread
        ) {

            return;

        }


        /*
         * Make sure the popup starts closed.
         */

        quickReplyOverlay.classList.remove(
            'is-open'
        );

        quickReplyOverlay.setAttribute(
            'aria-hidden',
            'true'
        );


        /*
         * Close button.
         */

        if (quickReplyClose) {

            quickReplyClose.addEventListener(
                'click',
                function (event) {

                    event.preventDefault();

                    closeQuickReply();

                }
            );

        }


        /*
         * Cancel button.
         */

        if (quickReplyCancel) {

            quickReplyCancel.addEventListener(
                'click',
                function (event) {

                    event.preventDefault();

                    closeQuickReply();

                }
            );

        }


        /*
         * Clicking the dark area outside the popup
         * closes the popup.
         */

        quickReplyOverlay.addEventListener(
            'click',
            function (event) {

                if (
                    event.target ===
                    quickReplyOverlay
                ) {

                    closeQuickReply();

                }

            }
        );


        /*
         * Prevent clicks inside the popup from
         * bubbling to the overlay.
         */

        quickReplyPopup.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

            }
        );


        /*
         * Submit protection.
         *
         * We intentionally do NOT prevent submission.
         * The normal /post.php handler should receive
         * the form normally.
         */

        quickReplyForm.addEventListener(
            'submit',
            function () {

                /*
                 * Nothing needed here.
                 *
                 * Leaving the form alone allows normal
                 * multipart/form-data submission.
                 */

            }
        );
    }


    /*
     * ---------------------------------------------------------
     * Open Quick Reply
     * ---------------------------------------------------------
     */

    function openQuickReply(threadId) {

        if (
            !quickReplyOverlay ||
            !quickReplyForm ||
            !quickReplyThread
        ) {

            return;

        }


        if (!threadId) {
            return;
        }


        previousActiveElement =
            document.activeElement;


        /*
         * Set the thread ID that post.php needs.
         */

        quickReplyThread.value =
            threadId;


        /*
         * Make sure the form is visible.
         */

        quickReplyOverlay.classList.add(
            'is-open'
        );


        quickReplyOverlay.setAttribute(
            'aria-hidden',
            'false'
        );


        document.body.classList.add(
            'quick-reply-open'
        );


        /*
         * Focus message field.
         */

        window.setTimeout(
            function () {

                if (quickReplyBody) {

                    quickReplyBody.focus();

                }

            },
            50
        );
    }


    /*
     * ---------------------------------------------------------
     * Close Quick Reply
     * ---------------------------------------------------------
     */

    function closeQuickReply() {

        if (!quickReplyOverlay) {
            return;
        }


        quickReplyOverlay.classList.remove(
            'is-open'
        );


        quickReplyOverlay.setAttribute(
            'aria-hidden',
            'true'
        );


        document.body.classList.remove(
            'quick-reply-open'
        );


        /*
         * Reset the form.
         */

        if (quickReplyForm) {

            quickReplyForm.reset();

        }


        /*
         * Clear thread ID after reset.
         */

        if (quickReplyThread) {

            quickReplyThread.value = '';

        }


        /*
         * Return focus to the button that opened
         * the popup.
         */

        if (
            previousActiveElement &&
            typeof previousActiveElement.focus ===
                'function'
        ) {

            previousActiveElement.focus();

        }


        previousActiveElement =
            null;
    }


    /*
     * ---------------------------------------------------------
     * Reply link handling
     * ---------------------------------------------------------
     *
     * Handles:
     *
     * <a
     *   href="#"
     *   class="reply-link"
     *   data-thread-id="123"
     * >
     *   [Reply]
     * </a>
     */

    document.addEventListener(
        'click',
        function (event) {

            var link =
                event.target.closest(
                    '.reply-link'
                );


            if (!link) {
                return;
            }


            var threadId =
                link.getAttribute(
                    'data-thread-id'
                );


            if (!threadId) {
                return;
            }


            /*
             * Stop the # link from changing the URL.
             */

            event.preventDefault();


            /*
             * Stop other click handlers from
             * interfering with the popup.
             */

            event.stopPropagation();


            openQuickReply(threadId);

        }
    );


    /*
     * ---------------------------------------------------------
     * Backwards-compatible toggleQuickReply()
     * ---------------------------------------------------------
     *
     * Older pomfIB templates may still call:
     *
     * toggleQuickReply(123)
     *
     * Keep the function available so older templates
     * do not break.
     */

    window.toggleQuickReply =
        function (threadId) {

            if (
                quickReplyOverlay &&
                quickReplyOverlay.classList.contains(
                    'is-open'
                )
            ) {

                closeQuickReply();

                return;

            }


            openQuickReply(threadId);

        };


    /*
     * ---------------------------------------------------------
     * Quote / Reply links
     * ---------------------------------------------------------
     *
     * Older templates may use:
     *
     * data-reply-link
     * data-post-id
     *
     * Keep this behavior.
     */

    document.addEventListener(
        'click',
        function (event) {

            var link =
                event.target.closest(
                    'a[data-reply-link]'
                );


            if (!link) {
                return;
            }


            /*
             * Do not interfere with the new
             * .reply-link popup.
             */

            if (
                link.classList.contains(
                    'reply-link'
                )
            ) {

                return;

            }


            var form =
                document.querySelector(
                    '.reply-form textarea[name="body"]'
                );


            if (!form) {
                return;
            }


            event.preventDefault();


            form.focus();


            var postId =
                link.getAttribute(
                    'data-post-id'
                );


            if (postId) {

                form.value +=
                    '>>' +
                    postId +
                    '\n';

            }

        }
    );


    /*
     * ---------------------------------------------------------
     * No. links
     * ---------------------------------------------------------
     *
     * No.123 links use:
     *
     * /thread.php?b=g&id=12#p123
     *
     * If the post is already on the current page,
     * allow normal browser navigation and highlight it.
     *
     * If it is not on the current page, allow the browser
     * to navigate to the supplied thread URL normally.
     */

    document.addEventListener(
        'click',
        function (event) {

            var postLink =
                event.target.closest(
                    'a.post_no'
                );


            if (!postLink) {
                return;
            }


            var href =
                postLink.getAttribute(
                    'href'
                );


            if (!href) {
                return;
            }


            var hashPosition =
                href.indexOf('#p');


            if (hashPosition === -1) {
                return;
            }


            var postId =
                href.substring(
                    hashPosition + 2
                );


            if (
                !/^[0-9]+$/.test(postId)
            ) {

                return;

            }


            /*
             * If the post exists on this page,
             * use the hash navigation and highlight it.
             */

            var localPost =
                document.getElementById(
                    'p' + postId
                );


            if (localPost) {

                /*
                 * We deliberately do not preventDefault().
                 *
                 * The browser will update the hash,
                 * then hashchange will highlight the post.
                 */

                return;

            }


            /*
             * Otherwise this is a normal link to
             * another thread/page.
             *
             * Do not interfere.
             */

        }
    );


    /*
     * ---------------------------------------------------------
     * Thread reply form
     * ---------------------------------------------------------
     *
     * If URL contains #reply, focus the reply form.
     */

    function focusReplyForm() {

        var replyForm =
            document.querySelector(
                '.reply-form'
            );


        if (!replyForm) {
            return;
        }


        var textarea =
            replyForm.querySelector(
                'textarea[name="body"]'
            );


        if (!textarea) {
            return;
        }


        textarea.focus();
    }


    if (
        window.location.hash ===
        '#reply'
    ) {

        window.setTimeout(
            function () {

                focusReplyForm();

            },
            150
        );

    }


    /*
     * ---------------------------------------------------------
     * Prevent accidental image dragging
     * ---------------------------------------------------------
     */

    document.addEventListener(
        'dragstart',
        function (event) {

            if (
                event.target &&
                event.target.matches &&
                event.target.matches(
                    '.post-image.expanded-image'
                )
            ) {

                event.preventDefault();

            }

        }
    );


    /*
     * ---------------------------------------------------------
     * Initialization
     * ---------------------------------------------------------
     */

    function initialize() {

        initializeQuickReply();

        handleInitialHash();

    }


    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initialize
        );

    } else {

        initialize();

    }

})();

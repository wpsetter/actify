document.addEventListener("DOMContentLoaded", function (event) {

    if (typeof window !== 'undefined') {
        window.recaptchaLoaded = new Promise(function (resolve) {
            window.vueRecaptchaInit = resolve;
        });
    }
    var $actifyEl = jQuery('.actify-content-wrapper');
    var postId = $actifyEl.data('post-id');

    $actifyEl.mouseup(function () {

        if (!$app.showMegaModal && !$app.showTypoModal && !$app.showHighlightModal) {
            $app.shared.selection = window.getSelection().getRangeAt(0);
            $app.shared.text = window.getSelection().toString();
            $app.shared.boundingRect = window.getSelection().getRangeAt(0).getBoundingClientRect();

        }

    });

    Vue.component('modal-highlight', {
        template: '#modal-highlight',
        props: ['show', 'msgHighlight', 'highlightShared', 'recaptchaSiteKey', 'currentPostId'],
        data: function () {
            return {
                highlight: this.highlightShared,
                sitekey: this.recaptchaSiteKey,
                msg: this.msgHighlight,
                recaptchaID: false,
                showMessage: false,
                userText: '',
                ajaxResponse: false,
                postId: this.currentPostId
            }
        },
        mounted: function () {
            this.$nextTick(function () {

                var that = this;
                window.recaptchaLoaded.then(function () {
                    that.recaptchaID = grecaptcha.render(that.$refs.typoRecaptcha, {
                        'sitekey': that.sitekey,
                    });
                });
            });
        },
        methods: {
            addFontIcon: function () {
                return {
                    'fa': true,
                    'fa-check-circle': this.ajaxResponse,
                    'fa-exclamation-circle': !this.ajaxResponse
                };
            },
            afterLeave: function () {
                this.showMessage = false;
            },
            savePost: function () {
                var that = this;

                wp.ajax.post(
                    {
                        action: 'actify_save_highlight',
                        nonce: actify.highlight_nonce,
                        highlight: that.highlight.text,
                        recaptcha: grecaptcha.getResponse(that.recaptchaID),
                        postId: that.postId
                    }).done(function (response) {
                    that.ajaxResponse = true;
                }).fail(function (response) {
                    that.ajaxResponse = false;
                }).always(function (userText) {
                    that.userText = userText;
                    grecaptcha.reset(that.recaptchaID);
                    that.showMessage = true;
                    $tooltip.showTooltip = false;
                });

                setTimeout(function () {
                    that.$emit('close');
                }, 5000);


            },
            closeModal: function () {
                this.$emit('close');
                grecaptcha.reset(this.recaptchaID);
            }
        }
    });

    Vue.component('modal-report', {
        template: '#modal-report',
        props: ['show', 'msgReport', 'recaptchaSiteKey'],
        data: function () {
            return {
                desc: '',
                phone: '',
                msg: this.msgReport,
                sitekey: this.recaptchaSiteKey,
                recaptchaID: false,
                showMessage: false,
                userText: '',
                ajaxResponse: false
            }
        },
        mounted: function () {
            this.$nextTick(function () {

                var that = this;
                window.recaptchaLoaded.then(function () {
                    that.recaptchaID = grecaptcha.render(that.$refs.typoRecaptcha, {
                        'sitekey': that.sitekey,
                    });
                });
            });
        },
        computed: {},
        methods: {
            addFontIcon: function () {
                return {
                    'fa': true,
                    'fa-check-circle': this.ajaxResponse,
                    'fa-exclamation-circle': !this.ajaxResponse
                };
            },
            afterLeave: function () {
                this.showMessage = false;
            },
            savePost: function () {
                var that = this;

                wp.ajax.post(
                    {
                        action: 'actify_report_case',
                        nonce: actify.report_nonce,
                        phone: that.phone,
                        desc: that.desc,
                        recaptcha: grecaptcha.getResponse(that.recaptchaID)
                    }).done(function (response) {
                    that.ajaxResponse = true;
                }).fail(function (response) {
                    that.ajaxResponse = false;
                }).always(function (userText) {
                    that.userText = userText;
                    grecaptcha.reset(that.recaptchaID);
                    that.showMessage = true;
                    $tooltip.showTooltip = false;
                    that.note = "";
                });

                setTimeout(function () {
                    that.$emit('close');
                }, 5000);


            },
            closeModal: function () {
                this.desc = "";
                this.phone = "";
                this.$emit('close');
                grecaptcha.reset(this.recaptchaID);
            }
        }
    });

    Vue.component('modal-typo', {
        template: '#modal-typo',
        props: ['show', 'shared', 'msgTypo', 'recaptchaSiteKey'],
        data: function () {
            return {
                note: '',
                sitekey: this.recaptchaSiteKey,
                recaptchaID: false,
                showMessage: false,
                typoShared: this.shared,
                msg: this.msgTypo,
                userText: '',
                ajaxResponse: false
            }
        },


        mounted: function () {
            this.$nextTick(function () {

                var that = this;
                window.recaptchaLoaded.then(function () {
                    that.recaptchaID = grecaptcha.render(that.$refs.typoRecaptcha, {
                        'sitekey': that.sitekey,
                    });
                });
            });
        },
        methods: {
            addFontIcon: function () {
                return {
                    'fa': true,
                    'fa-check-circle': this.ajaxResponse,
                    'fa-exclamation-circle': !this.ajaxResponse
                };
            },
            afterLeave: function () {
                this.showMessage = false;
            },
            savePost: function () {
                var that = this;

                wp.ajax.post(
                    {
                        action: 'actify_report_mistake',
                        nonce: actify.mistake_nonce,
                        mistake: that.typoShared.text,
                        note: that.note,
                        recaptcha: grecaptcha.getResponse(that.recaptchaID)
                    }).done(function (response) {
                    that.ajaxResponse = true;
                }).fail(function (response) {
                    that.ajaxResponse = false;
                }).always(function (userText) {
                    that.userText = userText;
                    grecaptcha.reset(that.recaptchaID);
                    that.showMessage = true;
                    $tooltip.showTooltip = false;
                    that.note = "";
                });

                setTimeout(function () {
                    that.$emit('close');
                }, 5000);


            },
            closeModal: function () {
                this.note = "";
                this.$emit('close');
                grecaptcha.reset(this.recaptchaID);

            }
        }
    });

    var shared = {
        selection: '',
        boundingRect: '',
        text: ''
    };
    var $app = new Vue({
        el: '#vue-app',
        data: {
            shared: shared,
            showMegaModal: false,
            showTypoModal: false,
            showTooltip: false,
            showHighlightModal: false,
            msg: actify.msg,
            options: actify.options,
            postId: postId,
            highlights: []
        },
        beforeMount: function () {
            var that = this;

            wp.ajax.post(
                {
                    action: 'actify_get_highlights',
                    nonce: actify.get_highlights_nonce,
                    post_id: that.postId
                }).done(function (response) {
                that.highlights = response;
            });

        },
        mounted: function () {

            this.$nextTick(function () {

            });
        },
        updated: function () {
            this.$nextTick(function () {

            });
        },
        created: function () {
        },
        methods: {
            getHighlights: function () {
                var that = this;

                wp.ajax.post(
                    {
                        action: 'actify_get_highlights',
                        nonce: actify.get_highlights_nonce,
                        post_id: that.postId
                    }).done(function (response) {
                    // that.highlights = response;
                    that.highlights = Object.assign({}, that.highlights, response);
                });
            },
            onMouseUp: function () {

                if (!this.showMegaModal && !this.showTypoModal && !this.showHighlightModal) {
                    this.shared.selection = window.getSelection().getRangeAt(0);
                    this.shared.text = window.getSelection().toString();
                    this.shared.boundingRect = window.getSelection().getRangeAt(0).getBoundingClientRect();

                }
            },
            openTypoModal: function () {

                this.showTypoModal = true;

            },
            openHighlightModal: function () {

                this.showHighlightModal = true;

            },
            openMegaModal: function () {

                this.showMegaModal = true;

            }

        },
        watch: {
            highlights: function () {
                $keywords = [];
                this.highlights.forEach(function (item) {
                    $keywords.push(item.highlight);
                });

                jQuery(".actify-content-wrapper").mark($keywords, {
                    className: 'actify-mark',
                    separateWordSearch: false,
                    acrossElements: true
                });

            },
            selection: function (newValue) {

            },
            showMegaModal: function (newValue) {

                var el = document.querySelector("body");

                if (newValue) {
                    el.classList.add('modal-open');

                } else {
                    el.classList.remove('modal-open');

                }
            }
        }
    });

    var $tooltip = new Vue({
        el: '#vue-tooltip',
        data: {
            shared: shared,
            showTooltip: false,
            msg: actify.msg.tooltip,
            options: actify.options,
            postId: postId
        },
        computed: {
            showFacebookButton: function () {
                var appId = this.options.facebook_app_id.trim();
                return this.options.show_facebook_button == true && !!appId;
            }
        },
        methods: {
            tooltipStyle: function () {
                return {
                    position: 'absolute',
                    left: (this.shared.boundingRect.left + window.pageXOffset - 110 + this.shared.boundingRect.width / 2) + 'px',
                    top: (this.shared.boundingRect.top + window.pageYOffset - 80) + 'px',
                    backgroundColor: '#000000',
                    color: '#fff',
                    padding: '10px',
                }
            },
            highlight: function () {

                this.showTooltip = false;
                $app.openHighlightModal();

                var newSpan = document.createElement('span');
                // add the class to the 'spam'
                newSpan.setAttribute('class', 'green-span');
                this.shared.selection.surroundContents(newSpan);

            },
            twitterShare: function () {
                var intent = "https://twitter.com/intent/tweet?text=" + encodeURIComponent(this.shared.text) + "&url=" + encodeURIComponent(window.location.href);

                window.open(intent);
            },
            facebookShare: function () {

                FB.ui({
                    method: 'share',
                    mobile_iframe: true,
                    quote: this.shared.text,
                    href: window.location.href
                }, function (response) {

                });
            },
            openTypoModal: function () {
                this.showTooltip = false;
                $app.openTypoModal();
            }
        },
        watch: {
            'shared.text': function (selectionText) {

                this.showTooltip = !!selectionText.length;

            }
        }
    });
});
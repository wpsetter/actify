document.addEventListener("DOMContentLoaded", function (event) {

    var vueData = admin_vue_options;
    var $app = new Vue({
        el: '#options-app',
        data: {
            options: vueData.options,
            l18n: vueData.l18n
        },
        methods: {
            saveOptions: function () {

                var that = this;
                wp.ajax.post(
                    vueData.ajaxCallback,
                    that.options
                ).done(function (response) {
                    console.log('From Done Ajax');
                });

            }
        }
    });
});
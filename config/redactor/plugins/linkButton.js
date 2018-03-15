(function ($) {
    $.Redactor.prototype.linkButton = function () {
        return {
            langs: {
                en: {
                    "linkButton": "Link button"
                }
            },
            init: function () {
                var button = this.button.add('toggle-button', 'Toggle Button');
                this.button.setIcon(button, '<i class="plugin-linkbutton-toolbar-icon"></i>');
                this.button.addCallback(button, function () {
                    this.link.get().toggleClass('btn');
                    this.code.sync();
                });
            },
        };
    };
})(jQuery);
(function ($R) {
    $R.add('plugin', 'linkButton', {
        init: function (app) {
            this.app = app;
            this.toolbar = app.toolbar;
            this.selection = app.selection;
        },
        start: function () {
            var $button = this.toolbar.addButton('link-button', {
                title: 'Button',
                api: 'plugin.linkButton.toggle'
            });

            $button.setIcon('<i class="re-icon-plugin-linkbutton"></i>');
        },
        toggle: function () {
            var element = this.selection.getElement();
            if (element.nodeName === 'A') {
                var $node = $R.dom(element);
                $node.toggleClass('btn');
            } else {
                alert("Please select a link first before making it into a button.")
            }
        }
    });
})(Redactor);

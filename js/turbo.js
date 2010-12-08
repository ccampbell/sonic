/**
 * class for handling injecting JSON views rendered into the DOM
 *
 * @author Craig Campbell
 * @version 1.0 beta
 */
window.SonicTurbo = (function()
{
    /**
     * array of javascript files to load
     *
     * @var array
     */
    var _js_queue = [];

    /**
     * adds an array of css files to the document
     *
     * @param JSON
     * @return void
     */
    var _addCss = function(css)
    {
        for (i = 0; i < css.length; ++i) {
            _addCssFile(css[i]);
        }
    };

    /**
     * adds a single css file to the document
     *
     * @param string
     * @return void
     */
    var _addCssFile = function(filename)
    {
        var stylesheet = document.createElement("link");
        stylesheet.setAttribute("rel", "stylesheet");
        stylesheet.setAttribute("type", "text/css");
        stylesheet.setAttribute("href", filename);
        document.getElementsByTagName("head")[0].appendChild(stylesheet);
    };

    /**
     * loads the next javascript file from the queue
     *
     * @return void
     */
    var _processQueue = function()
    {
        if (_js_queue.length) {
            _addJsFile(_js_queue[0]);
        }
    };

    /**
     * adds a single js fileto the document
     *
     * @param string
     * @return void
     */
    var _addJsFile = function(filename)
    {
        var body = document.getElementsByTagName("body")[0];
        var script = document.createElement("script");
        script.src = filename;

        var done = false;
        script.onload = script.onreadystatechange = function() {
            if (!done && (!this.readyState || this.readyState === "loaded" || this.readyState === "complete")) {
                done = true;

                // remove this item from the queue and process the next item
                _js_queue.splice(0, 1);
                _processQueue();

                // Handle memory leak in IE
                script.onload = script.onreadystatechange = null;
                if (body && script.parentNode) {
                    body.removeChild(script);
                }
            }
        };

        body.appendChild(script);
    };

    return {
        /**
         * initialize method eats the noturbo cookie that was set if you do not have JS
         *
         * @return void
         */
        init : function()
        {
            document.cookie = 'noturbo=; expires=Thu, 01-Jan-70 00:00:01 GMT;';
        },

        /**
         * public function to render a view
         *
         * @param JSON
         * @return void
         */
        render: function(data)
        {
            // called via $this->_redirect() in a controller
            if (data.redirect) {
                window.location = data.redirect;
            }
            _addCss(data.css);
            document.title = data.title;
            document.getElementById(data.id).innerHTML = data.content;

            for (i in data.js) {
                _js_queue.push(data.js[i]);
            }

            _processQueue();
        }
    };
}) ();

if (window.addEventListener) {
    window.addEventListener('load', SonicTurbo.init, false);
}
else {
    window.attachEvent('onload', SonicTurbo.init);
}
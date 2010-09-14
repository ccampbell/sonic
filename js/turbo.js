var SonicTurbo = function()
{
    var _addCss = function(css)
    {
        for (i = 0; i < css.length; ++i) {
            _addCssFile(css[i]);
        }
    };

    var _addCssFile = function(filename)
    {
        var file = document.createElement("link");
        file.setAttribute("rel", "stylesheet");
        file.setAttribute("type", "text/css");
        file.setAttribute("href", filename);
        document.getElementsByTagName("head")[0].appendChild(file);
    };

    var _addJs = function(js)
    {
        for (i = 0; i < js.length; ++i) {
            _addJsFile(js[i]);
        }
    };

    var _addJsFile = function(filename)
    {
        var file = document.createElement("script");
        file.setAttribute("src", filename);
        document.getElementsByTagName("body")[0].appendChild(file);
    };

    return {
        init : function()
        {
            document.cookie = 'noturbo=; expires=Thu, 01-Jan-70 00:00:01 GMT;';
        },

        render: function(data)
        {
            if (data.redirect) {
                window.location = data.redirect;
            }
            _addCss(data.css);
            document.title = data.title;
            document.getElementById(data.id).innerHTML = data.content;
            _addJs(data.js);
        }
    };
} ();

window.onload = SonicTurbo.init();

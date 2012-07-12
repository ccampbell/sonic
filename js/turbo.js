/**
 * class for handling injecting JSON views rendered into the DOM
 *
 * @author Craig Campbell
 * @version 1.0 beta
 */
window.SonicTurbo = (function(doc)
{
    /**
     * array of javascript files to load
     *
     * @var array
     */
	var _js_queue = []; 
	var hide;
	var loadedCss = [];
	var loadedjs = [];
	var instanceCount = -1;
	var instance = [];
    /**
     * adds an array of css files to the document
     *
     * @param JSON
     * @return void
     */
    function _addCss(css, fragment) {
        for (var i = 0; i < css.length; ++i) {
		if(inArray(loadedCss, css[i])){
			_addCssFile(css[i], fragment);
			loadedCss.push(css[i]);
			instance[instanceCount]['css'].push(css[i]);
			hide = true;			
		}
        }
    }

    /**
     * adds a single css file to the document
     *
     * @param string
     * @return void
     */
    function _addCssFile(filename, fragment) {
	
		var stylesheet = doc.createElement("link");
		stylesheet.setAttribute("rel", "stylesheet");
		stylesheet.setAttribute("type", "text/css");
		stylesheet.setAttribute("href", filename);		
		doc.getElementsByTagName("head")[0].appendChild(stylesheet);
		
		var index = instanceCount;
		instance[index]['csscount']++;
	
	stylesheet.onload = function(){
		instance[index]['csscount']--;		
		if(instance[index]['csscount'] == 0){
			console.log('show_' + index);
			instance[index]['fragment'].setAttribute("style", "visibility:visible;");
			
		}		
	}
	stylesheet.onerror = stylesheet.onload;        
    }
    
	/**
	 * Checks loaded JS/CSS files
	 *
	 */
    
	function inArray(array, filename){
		
		for(var i=0;i<array.length;i++) {
			if(array[i] == filename) {
				return false;
			}	
		}
		return true;	
	}
    /**
     * loads the next javascript file from the queue
     *
     * @return void
     */
    function _processQueue() {
        if (_js_queue.length) {
		if(inArray(loadedjs, _js_queue[0])){
			_addJsFile(_js_queue[0]);
			loadedjs.push(_js_queue[0]); // flag js file as 'loaded'
		}
        }
    }
    /**
     * adds a single js fileto the document
     *
     * @param string
     * @return void
     */
    function _addJsFile(filename) {
        var body = doc.getElementsByTagName("body")[0],
            script = doc.createElement("script"),
            done = false;
	    
        script.src = filename;

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
    }

    return {
        /**
         * initialize method eats the noturbo cookie that was set if you do not have JS
         *
         * @return void
         */
        init : function()
        {
            //doc.cookie = 'noturbo=; expires=Thu, 01-Jan-70 00:00:01 GMT;';
        },

        /**
         * public function to render a view
         *
         * @param JSON
         * @return void
         */
        render: function(data)
        {
		var fragment = doc.getElementById(data.id);
		    instanceCount++;
		    instance.push({
					css: new Array(),
					fragment: fragment,
					csscount: 0
				});		   
		    hide = false;	
            // called via $this->_redirect() in a controller
            if (data.redirect) {
                window.location = data.redirect;
            }
	     _addCss(data.css, fragment);
	     
	    
	     if(hide){
		fragment.setAttribute("style", "visibility:hidden;");
	     }
	    fragment.setAttribute("class", "sonic_fragment opacity_transition");
	    fragment.innerHTML = data.content;
           
		if(data.title){
			doc.title = data.title;
		}
		else {
			doc.title = 'Home - TellyCards';
		}
	    
            for (var i in data.js) {		
			 _js_queue.push(data.js[i]);
            }

            _processQueue();
        }
    };
})(document);

if (window.addEventListener) {
    window.addEventListener('load', SonicTurbo.init, false);
}
else {
    window.attachEvent('onload', SonicTurbo.init);
}
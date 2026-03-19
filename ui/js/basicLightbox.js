/*! basicLightbox v5.0.4 | MIT License | https://github.com/electerious/basicLightbox */
(function(window){
  var basicLightbox = (function(){
    var create = function(html, options) {
      options = options || {};
      var instance = {};
      var elem = document.createElement('div');
      elem.className = 'basicLightbox';
      if (html instanceof Node) {
        elem.appendChild(html);
      } else {
        // Sicheres Parsen: <template> führt keine Scripts aus
        var _tpl = document.createElement('template');
        _tpl.innerHTML = String(html);
        elem.appendChild(_tpl.content.cloneNode(true));
      }
      var closable = options.closable !== false;
      var onShow = typeof options.onShow === 'function' ? options.onShow : function(){};
      var onClose = typeof options.onClose === 'function' ? options.onClose : function(){};
      instance.show = function(){
        document.body.appendChild(elem);
        onShow(instance);
      };
      instance.close = function(){
        if (elem.parentNode) elem.parentNode.removeChild(elem);
        onClose(instance);
      };
      if (closable) {
        elem.addEventListener('click', function(e){
          if (e.target === elem) instance.close();
        });
      }
      return instance;
    };
    return { create: create };
  })();
  window.basicLightbox = basicLightbox;
})(window);
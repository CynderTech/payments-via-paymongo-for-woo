(function($, window, undefined) {
  "use strict";
  console.log("registered?");
  $.fn.cleave = function(opts) {
    var defaults = { autoUnmask: false },
      options = $.extend(defaults, opts || {});

    return this.each(function() {
      var cleave = new Cleave("#" + this.id, options),
        $this = $(this);

      $this.data("cleave-auto-unmask", options["autoUnmask"]);
      $this.data("cleave", cleave);
    });
  };

  var origGetHook, origSetHook;

  if ($.valHooks.input) {
    origGetHook = $.valHooks.input.get;
    origSetHook = $.valHooks.input.set;
  } else {
    $.valHooks.input = {};
  }

  $.valHooks.input.get = function(el) {
    var $el = $(el),
      cleave = $el.data("cleave");

    if (cleave) {
      return $el.data("cleave-auto-unmask") ? cleave.getRawValue() : el.value;
    } else if (origGetHook) {
      return origGetHook(el);
    } else {
      return undefined;
    }
  };

  $.valHooks.input.set = function(el, val) {
    var $el = $(el),
      cleave = $el.data("cleave");

    if (cleave) {
      cleave.setRawValue(val);
      return $el;
    } else if (origSetHook) {
      return origSetHook(el);
    } else {
      return undefined;
    }
  };
})(jQuery, window);

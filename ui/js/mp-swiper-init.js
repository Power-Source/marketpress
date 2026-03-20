// Lightbox-Logik für Produktbilder
document.addEventListener('DOMContentLoaded', function() {
  if (!window.mpProductLightboxEnabled) return;
  if (typeof window.basicLightbox === 'undefined') {
    console.warn('MP Swiper init: basicLightbox is not available.');
    return;
  }
  var gallery = document.getElementById('mp-product-gallery');
  if (!gallery) return;
  gallery.addEventListener('click', function(e) {
    var img = e.target.closest('.swiper-slide img');
    var link = e.target.closest('.swiper-slide a');
    if (!img && !link) return;
    e.preventDefault();
    // Alle Bild-URLs sammeln
    var images = Array.from(gallery.querySelectorAll('.swiper-slide img')).map(function(img) {
      return img.getAttribute('src');
    });
    var clickedSrc = img ? img.getAttribute('src') : link.querySelector('img') ? link.querySelector('img').getAttribute('src') : null;
    var startIndex = images.indexOf(clickedSrc);
    // Lightbox-DOM aufbauen (kein innerHTML mit externen Daten)
    var _lbRoot = document.createElement('div');
    _lbRoot.className = 'mp-product-lightbox';
    var _lbClose = document.createElement('div');
    _lbClose.className = 'mp-product-lightbox-close';
    _lbClose.textContent = '\u00d7';
    _lbRoot.appendChild(_lbClose);
    var _swiperEl = document.createElement('div');
    _swiperEl.className = 'swiper mp-product-lightbox-gallery';
    _swiperEl.setAttribute('style', 'width:90vw;height:90vh;max-width:1200px;');
    var _swiperWrapper = document.createElement('div');
    _swiperWrapper.className = 'swiper-wrapper';
    images.forEach(function(url) {
      var _slide = document.createElement('div');
      _slide.className = 'swiper-slide';
      var _img = document.createElement('img');
      _img.src = url;
      _img.setAttribute('style', 'width:100%;height:auto;max-height:80vh;object-fit:contain;');
      _slide.appendChild(_img);
      _swiperWrapper.appendChild(_slide);
    });
    _swiperEl.appendChild(_swiperWrapper);
    var _pagination = document.createElement('div');
    _pagination.className = 'swiper-pagination';
    _swiperEl.appendChild(_pagination);
    var _btnNext = document.createElement('div');
    _btnNext.className = 'swiper-button-next';
    _swiperEl.appendChild(_btnNext);
    var _btnPrev = document.createElement('div');
    _btnPrev.className = 'swiper-button-prev';
    _swiperEl.appendChild(_btnPrev);
    _lbRoot.appendChild(_swiperEl);
    var instance = window.basicLightbox.create(_lbRoot, {
      closable: true,
      onShow: function() {
        var closeBtn = document.querySelector('.mp-product-lightbox-close');
        if (closeBtn) closeBtn.onclick = function() { instance.close(); };
        var swiper = new Swiper('.mp-product-lightbox-gallery', {
          loop: true,
          slidesPerView: 1,
          spaceBetween: 0,
          initialSlide: startIndex,
          pagination: { el: '.swiper-pagination', clickable: true },
          navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
          zoom: true
        });
        document.addEventListener('keydown', function(ev) {
          if (ev.key === 'Escape') instance.close();
        }, { once: true });
      }
    });
    instance.show();
  });
});
// Swiper is loaded globally via ui/swiper/swiper-bundle.min.js.
var Swiper = window.Swiper;

if (typeof Swiper === 'undefined') {
  console.error('MP Swiper init: window.Swiper is not available.');
}

window.initProductGallerySwiper = function(selector, options = {}) {
  if (typeof Swiper === 'undefined') {
    return null;
  }

  return new Swiper(selector, {
    // Standardoptionen, können durch options überschrieben werden
    loop: true,
    slidesPerView: 1,
    spaceBetween: 0,
    pagination: {
      el: '.swiper-pagination',
      clickable: true,
    },
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
    thumbs: options.thumbs || {},
    ...options
  });
};

// Lightbox-Logik für Produktbilder
document.addEventListener('DOMContentLoaded', function() {
  if (!window.mpProductLightboxEnabled) return;
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
    // Lightbox-HTML
    var lightboxHtml = '<div class="mp-product-lightbox">' +
      '<div class="mp-product-lightbox-close">&times;</div>' +
      '<div class="swiper mp-product-lightbox-gallery" style="width:90vw;height:90vh;max-width:1200px;">' +
      '<div class="swiper-wrapper">' +
      images.map(function(url) { return '<div class="swiper-slide"><img src="'+url+'" style="width:100%;height:auto;max-height:80vh;object-fit:contain;" /></div>'; }).join('') +
      '</div>' +
      '<div class="swiper-pagination"></div>' +
      '<div class="swiper-button-next"></div>' +
      '<div class="swiper-button-prev"></div>' +
      '</div>' +
      '</div>';
    var instance = basicLightbox.create(lightboxHtml, {
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
// Swiper-Bundle für Frontend einbinden
// Dies ist ein Platzhalter für die Integration von Swiper in das MarketPress-Plugin.
// Die Datei wird als Brücke dienen, um Swiper im Theme/Plugin zu laden und zu initialisieren.

import Swiper from '../swiper/swiper-bundle.min.mjs';
// ...existing code...

window.initProductGallerySwiper = function(selector, options = {}) {
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

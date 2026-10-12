(function () {
  ("use strict");

  // HTML Root Element
  const rootHtml = document.firstElementChild;
  let windowWidth = window.innerWidth;
  const minWidth = 991;

  const isRTL = rootHtml.getAttribute("dir") === "rtl";


  // Sidebar Start
  function showSidebar() {
    var sidebar = document.querySelector(".sidebar");
    if (sidebar) {
      sidebar.style.transform = "translateX(0%)";
    }
  }

  function hideSidebar() {
    var sidebar = document.querySelector(".sidebar");
    if (sidebar) {
      sidebar.style.transform = isRTL ? "translateX(105%)" : "translateX(-105%)";
    }
  }

  function createOverlay() {
    removeOverlay();
    const overlay = document.createElement("div");
    overlay.setAttribute("id", "sidebar-overlay");

    overlay.style.cssText = `
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100vh;
        background: rgb(0 0 0 / 30%);
        z-index: 100;
        `;
    document.body.appendChild(overlay);

    // Add event listener for the overlay here
    overlay.addEventListener("click", () => {
      hideSidebar();
      removeOverlay();
      sidebarVisible = false;
    });
  }

  function removeOverlay() {
    const emailOverlay = document.querySelector("#sidebar-overlay");
    emailOverlay && emailOverlay.remove();
  }

  var sidebarButton = document.querySelector("#menu-btn");
  var sidebarVisible = false;

  if (sidebarButton) {
    sidebarButton.addEventListener("click", () => {
      if (!sidebarVisible) {
        showSidebar();
        createOverlay();
      } else {
        hideSidebar();
        removeOverlay();
      }
      sidebarVisible = !sidebarVisible;
    });
  }

  function handleResize() {
    let windowWidth = window.innerWidth;
    if (windowWidth >= minWidth) {
      showSidebar();
      removeOverlay();
    } else {
      hideSidebar();
      removeOverlay();
    }
  }
  window.addEventListener("resize", handleResize);
  handleResize();
  // Sidebar End

  // Mega Menu
  const menuLinks = document.querySelectorAll(".menu-link");
  if (menuLinks) {
    let windowWidth = window.innerWidth;
    if (windowWidth <= minWidth) {
      menuLinks.forEach((link) => {
        link.addEventListener("click", (e) => {
          event.stopPropagation();

          const icon = link.querySelector("span");
          if (icon) {
            icon.classList.toggle("rotate-180");
          }

          const nextItem = link.nextElementSibling;
          if (nextItem) {
            nextItem.classList.toggle("show");
          }
        });
      });
    }
  }


  // Provider slider
  const providerSlider = document.querySelector(".providers-slider");
  if (providerSlider) {
    new Swiper(providerSlider, {
      slidesPerView: 3,
      spaceBetween: 30,
      loop: true,
      autoplay: {
        delay: 2000,
        disableOnInteraction: false,
      },
      navigation: {
        nextEl: ".button-next",
        prevEl: ".button-prev",
      },
      breakpoints: {
        320: {
          slidesPerView: 3,
          spaceBetween: 15,
        },
        768: {
          slidesPerView: 4,
          spaceBetween: 20,
        },
        1024: {
          slidesPerView: 5,
          spaceBetween: 30,
        },
        1200: {
          slidesPerView: 6,
          spaceBetween: 30,
        },

        1500: {
          slidesPerView: 7,
          spaceBetween: 30,
        },
      },
    });
  }



  // Hover Tabs
  const tabOnHover = document.querySelectorAll(".menu-feature");
  if (tabOnHover) {
    tabOnHover.forEach((itemHover) => {
      itemHover
        .querySelectorAll(".menu-feature-item")
        .forEach(function (tabBtn) {
          var tabTrigger = new bootstrap.Tab(tabBtn);
          tabBtn.addEventListener("mouseenter", function () {
            tabTrigger.show();
          });
        });
    });
  }
})();

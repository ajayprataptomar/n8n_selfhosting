(function () {
  "use strict";

  const rootHtml = document.firstElementChild;
  let animationsInitialized = false; // Prevent double initialization

  function isRTLEnabled() {
    return rootHtml.getAttribute("dir") === "rtl";
  }

  window.addEventListener("load", (event) => {
    if (animationsInitialized) return; // Prevent double execution
    animationsInitialized = true;

    gsap.registerPlugin(ScrollTrigger, ScrollSmoother, SplitText);

    // Clear any existing ScrollTriggers
    ScrollTrigger.getAll().forEach(trigger => trigger.kill());

    ScrollTrigger.config({
      autoRefreshEvents: 'visibilitychange,DOMContentLoaded,load, resize'
    });

    // Scroll smoother
    ScrollSmoother.create({
      wrapper: "#smooth-wrapper",
      content: "#smooth-content",
      smooth: 1,
      effects: true,
      smoothTouch: 0.1,
      normalizeScroll: true,
      ignoreMobileResize: true,
    });

    const marquee = document.querySelectorAll('.marquee');
    if (marquee) {
      marquee.forEach((e) => {
        // Create swiper carousel
        const carousel = e.querySelectorAll('.marquee-carousel');
  
        carousel.forEach((e) => {
          const items = e.querySelector('.marquee-items');
          const item = e.querySelectorAll('.marquee-item');
  
          e.classList.add('swiper-container');
          items.classList.add('swiper-wrapper');
          item.forEach((e) => e.classList.add('swiper-slide'));
  
          const slider = new Swiper(e, {
            slidesPerView: 'auto',
            loop: true,
            freeMode: true,
            freeModeMomentumBounce: false,
            freeModeMomentumVelocityRatio: 0.3
          });
        });
  
        // Scroll triggered movement
        const tl = new gsap.timeline();
  
        tl.set(carousel, { willChange: 'transform' });
  
        tl.fromTo(
          carousel[0],
          {
            x: -300
          },
          {
            x: 0,
            ease: 'none'
          },
          0
        );
  
        tl.fromTo(
          carousel[1],
          {
            x: 300
          },
          {
            x: 0,
            ease: 'none'
          },
          0
        );
  
        tl.fromTo(
          carousel[2],
          {
            x: -300
          },
          {
            x: 0,
            ease: 'none'
          },
          0
        );

        tl.fromTo(
          carousel[3],
          {
            x: 300
          },
          {
            x: 0,
            ease: 'none'
          },
          0
        );

        tl.set(carousel, { willChange: 'auto' });
  
        ScrollTrigger.create({
          trigger: e,
          animation: tl,
          start: 'top bottom',
          end: 'bottom top',
          scrub: 0.3,
          refreshPriority: -14
        });
      });
    }

    const header = document.querySelector(".header");

    if (header) { 
      // Sticky header animation
      gsap.to(header, {
        scrollTrigger: {
          trigger: ".header",
          start: "top top",
          end: "+=50",
          scrub: true,
          onEnter: () => document.querySelector(".header").classList.add("sticky"),
          onLeaveBack: () => document.querySelector(".header").classList.remove("sticky"),
        },
        duration: 0.2,
        ease: "power2.out",
        overwrite: "auto",
      });
  
      // Reset header styles when scrolling back to top
      gsap.to(header, {
        scrollTrigger: {
          trigger: ".header",
          start: "top top",
          end: "top top",
          scrub: true,
          onEnterBack: () => document.querySelector(".header").classList.remove("sticky"),
        },
        duration: 0.2,
        ease: "power2.out",
        overwrite: "auto",
      });
    }
    
    /**
     * Utility: Create batch animations for scroll triggers
     */
    function batchAnimations(targets, vars) {
      const interval = vars.interval || 0.2;
      const varsCopy = {};

      const proxyCallback = (callback) => {
        let batch = [];
        const delay = gsap
          .delayedCall(interval, () => {
            callback(batch);
            batch.length = 0;
          })
          .pause();

        return (self) => {
          if (!batch.length) delay.restart(true);
          batch.push(self.trigger);
          if (vars.batchMax && batch.length >= vars.batchMax) {
            delay.progress(2);
          }
        };
      };

      // Apply initial state if provided
      if (vars.onInit) {
        gsap.set(targets, { autoAlpha: 0 }); // Hide immediately
        vars.onInit(gsap.utils.toArray(targets));
      }

      for (let key in vars) {
        if (["onInit"].includes(key)) continue; // skip init
        varsCopy[key] = key.includes("Enter") || key.includes("Leave")
          ? proxyCallback(vars[key])
          : vars[key];
      }

      gsap.utils.toArray(targets).forEach((target) => {
        ScrollTrigger.create({ ...varsCopy, trigger: target });
      });
    }

    /**
     * Utility: Animate element in from a direction
     */
    function animateFrom(elem, direction = 1) {
      let x = 0, y = direction * 100;
      if (elem.classList.contains("fromLeft")) {
        x = isRTLEnabled() ? 100 : -100; 
        y = 0;
      } else if (elem.classList.contains("fromRight")) {
        x = isRTLEnabled() ? -100 : 100;
        y = 0;
      }

      gsap.fromTo(elem, { x, y, autoAlpha: 0 }, {
        duration: 1,
        x: 0,
        y: 0,
        autoAlpha: 1,
        ease: "expo.easeOut",
        overwrite: "auto",
      });
    }

    /**
     * Utility: Hide element instantly
     */
    function hideElement(elem) {
      gsap.set(elem, { autoAlpha: 0 });
    }

    // Banner quote animation
    const banner = document.querySelector(".banner");
    if (banner) {
      const split = new SplitText(".quote-title", { type: "words,chars" });
      gsap.from(split.chars, {
        duration: 1.5,
        opacity:0,
        y: 20,
        ease: "power3",
        stagger: {
          each: 0.03,
        },
        onComplete: () => split.revert(),
      });
    }

    // Title split animations
    const titles = document.querySelectorAll(".title-anim");
    if (titles.length) {
      function setupTitleSplits() {
        titles.forEach((title) => {
          // Better cleanup - kill all related ScrollTriggers first
          if (title.anim) {
            title.anim.scrollTrigger?.kill();
            title.anim.kill();
            title.split?.revert();
          }
          
          title.split = new SplitText(title, {
            type: "lines,words,chars",
            linesClass: "split-line",
          });
          
          gsap.set(title, { autoAlpha: 0 });
          
          title.anim = gsap.from(title.split.chars, {
            scrollTrigger: {
              trigger: title,
              start: "top 85%",
              end: "bottom 15%",
              toggleActions: "play none none reverse",
              onEnter: () => gsap.set(title, { autoAlpha: 1 }),
              onLeave: () => gsap.set(title, { autoAlpha: 1 }), // Keep visible when leaving
              onEnterBack: () => gsap.set(title, { autoAlpha: 1 }), // Ensure visible on scroll back
            },
            duration: 0.6,
            ease: "circ.out",
            opacity: 0,
            stagger: 0.02,
          });
        });
      }
      
      // Remove the refresh listener to prevent re-initialization
      // ScrollTrigger.addEventListener("refresh", setupTitleSplits);
      setupTitleSplits();
    }

    const lineAnims = document.querySelectorAll(".lines-anim");
    if (lineAnims.length) {
      function setupLineSplits() {
        lineAnims.forEach((lineAnim) => {
          // Better cleanup
          if (lineAnim.anim) {
            lineAnim.anim.scrollTrigger?.kill();
            lineAnim.anim.kill();
            lineAnim.split?.revert();
          }
          
          lineAnim.split = new SplitText(lineAnim, {
            type: "lines",
            linesClass: "split-line"
          });

          lineAnim.anim = gsap.from(lineAnim.split.lines, {
            rotationX: -50,
            transformOrigin: "50% 50% -20px",
            opacity: 0,
            duration: 0.8,
            ease: "power3",
            stagger: 0.25,
            scrollTrigger: {
              trigger: lineAnim,
              start: "top 85%", 
              end: "bottom 15%",
              toggleActions: "play none none reverse",
            }
          });
        });
      }

      // Remove the refresh listener to prevent re-initialization
      // ScrollTrigger.addEventListener("refresh", setupLineSplits);
      setupLineSplits();
    }

    // Batch card animations
    const fadeItems = document.querySelectorAll(".fade-item");
    if (fadeItems.length > 0) {
        batchAnimations(".fade-item", {
          interval: 0.1,
          batchMax: 4,
    
          // Initial hidden state
          onInit: (els) => gsap.set(els, { autoAlpha: 0, y: 30, scale: 0.95 }),
    
          // Enter animation (wave effect)
          onEnter: (els) => gsap.to(els, {
            autoAlpha: 1,
            y: 0,
            scale: 1,
            duration: 0.8,
            ease: "power3.out",
            stagger: {
              each: 0.12,        
              from: "start",     
              ease: "power2.out"
            },
            delay: 0.05           
          }),
    
          // Remove onLeave to prevent disappearing
          // onLeave: (els) => gsap.to(els, {
          //   autoAlpha: 0,
          //   y: 30,
          //   scale: 0.95,
          //   duration: 0.5,
          //   ease: "power1.in",
          //   stagger: { each: 0.05, from: "end" }
          // }),
    
          // Enter back (ensure visibility)
          onEnterBack: (els) => gsap.to(els, {
            autoAlpha: 1,
            y: 0,
            scale: 1,
            duration: 0.8,
            ease: "power3.out",
            stagger: {
              each: 0.12,
              from: "end",
              ease: "power2.out"
            },
            delay: 0.05
          }),
    
          // Remove onLeaveBack to prevent disappearing when scrolling up
          // onLeaveBack: (els) => gsap.to(els, {
          //   autoAlpha: 0,
          //   y: -30,
          //   scale: 0.95,
          //   duration: 0.5,
          //   ease: "power1.in",
          //   stagger: { each: 0.05, from: "start" }
          // }),
        });
     }

    // Generic reveal animations
    gsap.utils.toArray(".gs_reveal").forEach((elem) => {
      hideElement(elem);
      ScrollTrigger.create({
        trigger: elem,
        start: "top 85%",
        end: "bottom 15%",
        toggleActions: "play none none reverse",
        onEnter: () => animateFrom(elem),
        onEnterBack: () => animateFrom(elem, -1),
        // Remove onLeave to prevent elements from disappearing
      });
    });

    // Final refresh after everything is set up
    ScrollTrigger.refresh();
    document.documentElement.classList.remove("is-loading");
  });
})();
/**
 * @file
 * The carousel's arrows, dots, keyboard handling and optional rotation.
 *
 * None of this is required for the carousel to work: the track is a scroll-snap
 * scroller, so swiping and trackpad scrolling happen in CSS. What is added here
 * is everything that needs to know how many slides there are, or where the
 * track currently sits.
 *
 * It is a Drupal behavior rather than FormaTheme's ES-module component pattern
 * on purpose. The theme's pattern imports its own lib/ by relative path, which
 * a module cannot do without hard-coding the theme's location in contrib, and
 * behaviors re-attach when Canvas re-renders a page in the editor.
 */

((Drupal, once) => {
  /**
   * Whether the page is being rendered inside the Canvas editor.
   *
   * Matches FormaTheme's own check. Reaching into window.parent can throw, so
   * a failure is read as "not the editor" - the front end is the common case.
   *
   * @return {boolean}
   *   True when this is the editor's canvas, not a page preview.
   */
  const inCanvasEditor = () => {
    try {
      return Boolean(
        window.parent &&
          window.parent !== window &&
          window.parent.drupalSettings &&
          window.parent.drupalSettings.canvas &&
          !window.parent.document.body.querySelector("[class^=_PagePreviewIframe]"),
      );
    } catch (e) {
      return false;
    }
  };

  const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  class Carousel {
    constructor(el) {
      this.el = el;
      this.track = el.querySelector("[data-carousel-track]");
      this.dotsEl = el.querySelector("[data-carousel-dots]");
      this.toggleEl = el.querySelector("[data-carousel-toggle]");
      this.index = 0;
      this.dots = [];
      this.timer = null;
      // Rotation is off in the editor: slides must hold still to be edited.
      this.rotates = el.hasAttribute("data-carousel-autoplay") && !inCanvasEditor();
      this.interval = (parseInt(el.dataset.carouselInterval, 10) || 6) * 1000;
    }

    get slides() {
      // Every direct child is a slide, whatever component rendered it.
      return Array.from(this.track.children);
    }

    init() {
      if (!this.track) {
        return;
      }

      this.onKeydown = this.onKeydown.bind(this);
      this.onScroll = this.onScroll.bind(this);
      this.onVisibilityChange = this.onVisibilityChange.bind(this);

      this.track.addEventListener("keydown", this.onKeydown);
      // Pointer and keyboard travel through the track both count as the
      // visitor driving, so the dots follow a swipe as well as an arrow press.
      this.track.addEventListener("scroll", this.onScroll, { passive: true });

      this.el.querySelectorAll("[data-carousel-prev]").forEach((button) => {
        button.addEventListener("click", () => {
          this.stop();
          this.go(this.index - 1);
        });
      });

      this.el.querySelectorAll("[data-carousel-next]").forEach((button) => {
        button.addEventListener("click", () => {
          this.stop();
          this.go(this.index + 1);
        });
      });

      if (this.toggleEl) {
        this.toggleEl.addEventListener("click", () => {
          if (this.timer) {
            this.stop();
          } else {
            this.start();
          }
        });
      }

      // Canvas adds and removes slides while the editor works, and a visitor
      // never sees a change here, so rebuilding the dots costs nothing.
      this.observer = new MutationObserver(() => this.sync());
      this.observer.observe(this.track, { childList: true });

      this.sync();
      this.el.classList.add("carousel--ready");

      if (this.rotates) {
        this.el.addEventListener("pointerenter", () => this.pause());
        this.el.addEventListener("pointerleave", () => this.resume());
        this.el.addEventListener("focusin", () => this.pause());
        this.el.addEventListener("focusout", () => this.resume());
        this.el.addEventListener("pointerdown", () => this.stop());
        document.addEventListener("visibilitychange", this.onVisibilityChange);
        this.start();
      }
    }

    /**
     * Rebuilds everything that depends on the number of slides.
     */
    sync() {
      const slides = this.slides;

      slides.forEach((slide, i) => {
        // Only slides this module rendered get a role, so an announced
        // position is never written onto an editor's link or card, where
        // role="group" would bury the element's real semantics.
        if (!slide.matches("[data-carousel-slide]")) {
          return;
        }
        const own = slide.dataset.carouselSlideLabel;
        const position = Drupal.t("@current of @total", {
          "@current": i + 1,
          "@total": slides.length,
        });
        slide.setAttribute("aria-label", own ? `${own} (${position})` : position);
      });

      if (this.dotsEl && this.dots.length !== slides.length) {
        this.dotsEl.textContent = "";
        this.dots = slides.map((slide, i) => {
          const dot = document.createElement("button");
          dot.type = "button";
          dot.className = "carousel__dot";
          dot.innerHTML = `<span class="visually-hidden">${Drupal.t("Slide @number", { "@number": i + 1 })}</span>`;
          dot.addEventListener("click", () => {
            this.stop();
            this.go(i);
          });
          this.dotsEl.appendChild(dot);
          return dot;
        });
      }

      this.mark();
    }

    /**
     * Scrolls to a slide, wrapping around at either end.
     *
     * Wrapping keeps the arrows live at the ends, which is also what lets
     * rotation continue past the last slide instead of stalling there.
     *
     * @param {number} target
     *   The slide to move to, before wrapping.
     */
    go(target) {
      const slides = this.slides;
      if (!slides.length) {
        return;
      }

      const index = (target + slides.length) % slides.length;
      const left = slides[index].offsetLeft - slides[0].offsetLeft;

      this.track.scrollTo({
        left,
        behavior: reducedMotion() ? "auto" : "smooth",
      });

      this.index = index;
      this.mark();
    }

    /**
     * Reads the current slide back off the track's scroll position.
     *
     * Measuring beats trusting a stored index: a swipe, a trackpad and the
     * browser's own keyboard scrolling all move the track without asking.
     */
    onScroll() {
      window.clearTimeout(this.scrollEnd);
      this.scrollEnd = window.setTimeout(() => {
        const slides = this.slides;
        if (!slides.length) {
          return;
        }

        const origin = slides[0].offsetLeft;
        const position = this.track.scrollLeft;
        let nearest = 0;
        let shortest = Infinity;

        slides.forEach((slide, i) => {
          const distance = Math.abs(slide.offsetLeft - origin - position);
          if (distance < shortest) {
            shortest = distance;
            nearest = i;
          }
        });

        if (nearest !== this.index) {
          this.index = nearest;
          this.mark();
        }
      }, 100);
    }

    mark() {
      this.dots.forEach((dot, i) => {
        if (i === this.index) {
          dot.setAttribute("aria-current", "true");
        } else {
          dot.removeAttribute("aria-current");
        }
      });
    }

    onKeydown(event) {
      const moves = {
        ArrowLeft: () => this.go(this.index - 1),
        ArrowRight: () => this.go(this.index + 1),
        Home: () => this.go(0),
        End: () => this.go(this.slides.length - 1),
      };

      if (!moves[event.key]) {
        return;
      }

      // The browser scrolls the track on arrow keys too; stepping slide by
      // slide instead of by a fixed distance is the whole point of handling it.
      event.preventDefault();
      this.stop();
      moves[event.key]();
    }

    start() {
      // Nothing moves on its own for a visitor who asked for reduced motion.
      if (!this.rotates || this.timer || reducedMotion()) {
        return;
      }
      this.timer = window.setInterval(() => this.go(this.index + 1), this.interval);
      this.setToggle(true);
    }

    /**
     * Stops rotation for good: the visitor has taken over, or pressed pause.
     */
    stop() {
      window.clearInterval(this.timer);
      this.timer = null;
      this.paused = false;
      this.setToggle(false);
    }

    /**
     * Holds rotation while the visitor is hovering, reading or tabbing through.
     */
    pause() {
      if (!this.timer) {
        return;
      }
      window.clearInterval(this.timer);
      this.timer = null;
      this.paused = true;
    }

    resume() {
      if (this.paused) {
        this.paused = false;
        this.start();
      }
    }

    onVisibilityChange() {
      if (document.hidden) {
        this.pause();
      } else {
        this.resume();
      }
    }

    /**
     * Swaps the pause button's glyph and its label.
     *
     * @param {boolean} running
     *   Whether slides are currently advancing.
     */
    setToggle(running) {
      if (!this.toggleEl) {
        return;
      }

      const label = this.toggleEl.querySelector("[data-carousel-toggle-label]");
      const pause = this.toggleEl.querySelector("[data-carousel-glyph-pause]");
      const play = this.toggleEl.querySelector("[data-carousel-glyph-play]");

      if (label) {
        label.textContent = running
          ? this.toggleEl.dataset.labelPause
          : this.toggleEl.dataset.labelPlay;
      }
      if (pause && play) {
        pause.toggleAttribute("hidden", !running);
        play.toggleAttribute("hidden", running);
      }
    }

    destroy() {
      this.stop();
      window.clearTimeout(this.scrollEnd);
      this.track.removeEventListener("keydown", this.onKeydown);
      this.track.removeEventListener("scroll", this.onScroll);
      document.removeEventListener("visibilitychange", this.onVisibilityChange);
      if (this.observer) {
        this.observer.disconnect();
      }
    }
  }

  // Instances are kept per element so detach can shut their timers down.
  const instances = new WeakMap();

  Drupal.behaviors.mariaCarousel = {
    attach(context) {
      once("maria-carousel", "[data-carousel]", context).forEach((el) => {
        const carousel = new Carousel(el);
        instances.set(el, carousel);
        carousel.init();
      });
    },
    detach(context) {
      context.querySelectorAll("[data-carousel]").forEach((el) => {
        const carousel = instances.get(el);
        if (carousel) {
          carousel.destroy();
          instances.delete(el);
        }
      });
    },
  };
})(Drupal, once);

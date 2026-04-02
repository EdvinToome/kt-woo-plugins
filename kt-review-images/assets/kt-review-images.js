document.addEventListener("DOMContentLoaded", () => {
  const { tabRatingAriaPrefix } = window.ktReviewImagesConfig;
  const reviewRoot = document.querySelector(".kt-review-images");
  const lightbox = document.querySelector(".kt-review-images__lightbox");

  if (reviewRoot) {
    const reviewsTabLink = document.querySelector("#tab-title-reviews a");
    const averageRating = Number(reviewRoot.dataset.ktReviewAverageRating || 0);
    const reviewCount = Number(reviewRoot.dataset.ktReviewCount || 0);

    if (
      reviewsTabLink &&
      averageRating > 0 &&
      !reviewsTabLink.querySelector(".kt-review-images__tab-summary")
    ) {
      const summary = document.createElement("span");
      summary.className = "kt-review-images__tab-summary";
      summary.setAttribute("aria-label", `${tabRatingAriaPrefix} ${averageRating}/5`);

      const stars = document.createElement("span");
      stars.className = "kt-review-images__tab-stars";
      stars.textContent = "★★★★★";

      const count = document.createElement("span");
      count.className = "kt-review-images__tab-count";
      count.textContent = String(reviewCount);

      summary.append(stars, count);
      reviewsTabLink.append(summary);
    }
  }

  if (!lightbox) {
    return;
  }

  const image = lightbox.querySelector(".kt-review-images__lightbox-image");
  const triggers = document.querySelectorAll("[data-kt-review-lightbox]");
  const closers = lightbox.querySelectorAll("[data-kt-review-close]");

  const closeLightbox = () => {
    lightbox.hidden = true;
    image.src = "";
    image.alt = "";
    document.body.style.overflow = "";
  };

  const openLightbox = (src, author) => {
    image.src = src;
    image.alt = author ? `${author} review image` : "Review image";
    lightbox.hidden = false;
    document.body.style.overflow = "hidden";
  };

  triggers.forEach((trigger) => {
    trigger.addEventListener("click", () => {
      openLightbox(
        trigger.dataset.ktReviewLightbox || "",
        trigger.dataset.ktReviewAuthor || "",
      );
    });
  });

  closers.forEach((closer) => {
    closer.addEventListener("click", closeLightbox);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !lightbox.hidden) {
      closeLightbox();
    }
  });
});

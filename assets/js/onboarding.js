/* Onboarding: follow/unfollow recommended chefs via the existing follow API.
 * (Interest chips are pure CSS via :checked — no JS needed there.) */
(function () {
  "use strict";

  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".ob-follow-btn");
    if (!btn) return;

    const card = btn.closest(".ob-person");
    const chefId = card && card.getAttribute("data-user-id");
    if (!chefId) return;

    const following = btn.getAttribute("data-following") === "1";
    const action = following ? "unfollow" : "follow";

    btn.disabled = true;
    fetch("api/follow.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ chef_id: parseInt(chefId, 10), action: action }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data && data.success) {
          const nowFollowing = action === "follow";
          btn.setAttribute("data-following", nowFollowing ? "1" : "0");
          btn.textContent = nowFollowing ? "Following" : "Follow";
          btn.classList.toggle("is-following", nowFollowing);
        }
      })
      .catch(() => {})
      .finally(() => {
        btn.disabled = false;
      });
  });
})();

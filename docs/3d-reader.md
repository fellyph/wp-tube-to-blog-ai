# CreatorStack 3D Reader

An experimental plugin in the CreatorStack suite (`creatorstack-3d-reader.php`)
that renders the live post page into WebGL via the new **HTML-in-Canvas API**
and plays a 3D paper-tear transition when the reader navigates to the
next/previous post.

## The HTML-in-Canvas API (research summary, July 2026)

The API was announced at Google I/O 2026 and is in **origin trial in
Chrome 148–150**. It lets you draw live DOM content into a 2D canvas, a WebGL
texture, or a WebGPU texture while preserving accessibility, find-in-page,
translation, and DevTools inspection. No other engine (Firefox, Safari) has
committed to implementing it yet, so it must always be treated as progressive
enhancement.

Three primitives (spec: [WICG/html-in-canvas](https://github.com/WICG/html-in-canvas)):

1. **`layoutsubtree` attribute** on `<canvas>` — opts direct children into
   layout and hit testing. Children get a stacking context, become a
   containing block, and gain paint containment. They are *not* painted to the
   screen unless explicitly drawn into the canvas.
2. **Drawing methods** — `ctx.drawElementImage(element, dx, dy, …)` for 2D
   contexts (returns a `DOMMatrix` you can assign to `element.style.transform`
   to keep hit testing aligned with the drawn position);
   `gl.texElementImage2D(target, internalformat, element, config)` for WebGL;
   `queue.copyElementImageToTexture(…)` for WebGPU. The element must be a
   direct child of the canvas. Earlier builds used the names `drawElement` /
   `texElement2D`, which keep working until M145 — the frontend script feature
   detects both.
3. **`paint` event + `canvas.requestPaint()`** — fires after intersection
   observer steps when the rendering of any canvas child has changed, with a
   `changedElements` list. Draw commands issued inside the handler appear in
   the current frame.

Additional pieces: `canvas.captureElementImage(element)` produces a
transferable `ElementImage` for OffscreenCanvas/worker rendering, and
`getElementTransform()` computes the CSS transform for a given draw transform.

Security model: rendering is "read-back-allowed" — cross-origin images/iframes,
visited-link state, system theme colors, spell-check markers, and IME UI are
excluded from the drawn output.

Enabling it:

- Local testing: `chrome://flags/#canvas-draw-element` (Chromium 147+,
  Chrome Canary 149+ recommended).
- Production: register the origin at
  [developer.chrome.com/origintrials](https://developer.chrome.com/origintrials)
  and paste the token into **Settings → 3D Reader**; the plugin prints it as an
  `origin-trial` meta tag on single posts.

Sources: [WICG explainer](https://github.com/WICG/html-in-canvas),
[Chrome origin trial announcement](https://developer.chrome.com/blog/html-in-canvas-origin-trial),
[blink-dev intent](https://groups.google.com/a/chromium.org/g/blink-dev/c/LYJyOdLbOfY),
[byteiota deep dive](https://byteiota.com/html-in-canvas-api-draw-live-dom-inside-webgl-chrome-2026/),
[Frontend Masters experiments](https://frontendmasters.com/blog/the-web-is-fun-again-first-experiments-with-html-in-canvas/).

## How the tear-down transition works

`assets/js/reader-3d.js`, orchestrated by `includes/class-reader-3d.php`:

1. On single posts the plugin prints floating next/previous buttons plus a
   JSON block (`#wttba-r3d-data`) with adjacent-post URLs. Clicks on
   `a[rel=next]`, `a[rel=prev]`, or the floating nav are intercepted when the
   API is available (and `prefers-reduced-motion` is off); hovering prefetches
   the destination.
2. A fixed, viewport-sized `<canvas layoutsubtree>` overlay is appended to
   `<body>`. The current page is **cloned** into the canvas as a direct child
   (scripts/iframes/media stripped, shifted by `-scrollY` so the visible slice
   lines up), then uploaded as WebGL texture 0 via `texElementImage2D` after
   waiting for the canvas `paint` event. The overlay now covers the page with
   an identical WebGL rendering of it.
3. The next post is fetched and soft-swapped into the document
   (`DOMParser` → body swap, head stylesheet sync, `history.pushState`), and a
   second snapshot of the new page becomes texture 1.
4. A ~1.2 s shader animation runs: a 96×96 grid mesh renders the old page
   torn along a procedurally jagged vertical line — the two halves separate,
   curl, and fall downward with a lit paper-fiber edge — while the new page
   settles underneath from a slight 3D tilt/scale to flat. Then the overlay is
   destroyed.

Every step is guarded: unsupported browser, missing WebGL, shader failure,
fetch timeout, or a never-firing `paint` event all fall back to normal
navigation (`location.assign`).

## Settings

**Settings → 3D Reader**

- **Enable 3D transitions** (`wttba_reader3d_enabled`, default on)
- **Origin trial token** (`wttba_reader3d_ot_token`) — printed as
  `<meta http-equiv="origin-trial">` on single posts
- **Content selector** (`wttba_reader3d_selector`) — optional CSS selector to
  swap only part of the page during soft navigation instead of the whole body

## Known limitations

- Chromium-only while the origin trial runs; everyone else gets regular
  navigation (by design).
- Soft navigation does not re-execute theme scripts on the incoming page;
  interactive theme widgets may need a full reload (the back button always
  does a full reload).
- Cross-origin images render blank inside the snapshot per the API's
  read-back-allowed security model.
- The vertical orientation of `texElementImage2D` uploads relative to
  `UNPACK_FLIP_Y_WEBGL` is still being specified; if pages render upside down
  in a future build, flip the `vUv` mapping in the shaders.

import "../css/qna.css"

// Turbo must be provided by the project, not by this bundle: whether Turbo Drive
// is on is a site-wide decision. Activate either "huh_ux_turbo_encore" (with
// Drive) or "huh_ux_turbo_encore_no_drive" (without) from
// heimrichhannot/contao-ux-turbo-encore in the layout or page settings. Both are
// head scripts and expose the instance as window.Turbo; this bundle works with
// either. Importing @hotwired/turbo here would bundle a second copy and would
// silently impose one of the two choices on the project.
const Turbo = window.Turbo

if (!Turbo) {
    // Without Turbo the frames below never load. Say so instead of failing quietly.
    console.error(
        "[qna] window.Turbo is missing. Activate the encore entry "
        + '"huh_ux_turbo_encore" or "huh_ux_turbo_encore_no_drive" for this page.',
    )
}

const BACKOFF_FACTOR = 2
const frames = new Map()

function readInterval(frame, name) {
    const dynamicContent = frame.querySelector("[data-qna-poll-interval]")
    const source = name === "pollInterval" && dynamicContent ? dynamicContent : frame
    const value = Number(name === "pollInterval" ? source.dataset.qnaPollInterval : frame.dataset[name])

    return Number.isFinite(value) && value > 0 ? value : null
}

function clearTimer(state) {
    if (state.timer !== null) {
        window.clearTimeout(state.timer)
        state.timer = null
    }
}

function frameIsInteracting(frame) {
    return frame.matches(":focus-within")
        || frame.hasAttribute("busy")
        || frame.querySelector("[aria-busy='true']") !== null
}

function removeFrame(frame) {
    const state = frames.get(frame)

    if (state) {
        clearTimer(state)
        frames.delete(frame)
    }
}

function schedule(frame) {
    const state = frames.get(frame)

    if (!state || !frame.isConnected || document.hidden) {
        return
    }

    clearTimer(state)

    const interval = readInterval(frame, "pollInterval")
    const maximum = readInterval(frame, "qnaPollMaxInterval")

    if (interval === null || maximum === null) {
        removeFrame(frame)
        return
    }

    const delay = Math.min(interval * BACKOFF_FACTOR ** state.failures, maximum)
    state.timer = window.setTimeout(() => reload(frame), delay)
}

function reload(frame) {
    const state = frames.get(frame)

    if (!state || !frame.isConnected || document.hidden) {
        schedule(frame)
        return
    }

    clearTimer(state)

    if (frameIsInteracting(frame)) {
        schedule(frame)
        return
    }

    try {
        Promise.resolve(frame.reload()).catch(() => markFailure(frame))
    } catch {
        markFailure(frame)
    }
}

function markFailure(frame) {
    const state = frames.get(frame)

    if (!state) {
        return
    }

    state.failures += 1
    schedule(frame)
}

function addFrame(frame) {
    if (frames.has(frame) || !frame.hasAttribute("src")) {
        return
    }

    frames.set(frame, { failures: 0, timer: null })
    schedule(frame)
}

function discoverFrames(root = document) {
    if (root instanceof Element && root.matches("turbo-frame[data-qna-poll]")) {
        addFrame(root)
    }

    root.querySelectorAll?.("turbo-frame[data-qna-poll]").forEach(addFrame)
}

document.addEventListener("turbo:frame-load", (event) => {
    const frame = event.target
    const state = frames.get(frame)

    if (state) {
        state.failures = 0
        schedule(frame)
    }
})

document.addEventListener("turbo:before-frame-render", (event) => {
    const frame = event.target

    if (
        frame instanceof Element
        && frame.matches('turbo-frame[data-qna-poll][refresh="morph"]')
        && typeof Turbo?.morphTurboFrameElements === "function"
    ) {
        event.detail.render = Turbo.morphTurboFrameElements
    }
})

document.addEventListener("turbo:fetch-request-error", (event) => {
    if (event.target instanceof Element && event.target.matches("turbo-frame[data-qna-poll]")) {
        event.preventDefault()
        markFailure(event.target)
    }
})

document.addEventListener("turbo:frame-missing", (event) => {
    if (event.target instanceof Element && event.target.matches("turbo-frame[data-qna-poll]")) {
        event.preventDefault()
        markFailure(event.target)
    }
})

document.addEventListener("turbo:before-cache", () => {
    frames.forEach(clearTimer)
    frames.clear()
})

document.addEventListener("turbo:load", () => discoverFrames())

document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
        frames.forEach(clearTimer)
    } else {
        frames.forEach((state, frame) => schedule(frame))
    }
})

new MutationObserver(() => {
    frames.forEach((state, frame) => {
        if (!frame.isConnected) {
            removeFrame(frame)
        }
    })

    discoverFrames()
}).observe(document.documentElement, { childList: true, subtree: true })

discoverFrames()

const submittedStageFocus = new WeakMap()

document.addEventListener("submit", (event) => {
    const form = event.target
    const frame = form.closest?.('[data-qna-frame="stage"]')

    if (frame && form.matches(".qna-answer-form") && event.submitter?.id) {
        submittedStageFocus.set(frame, event.submitter.id)
    }
}, true)

document.addEventListener("turbo:submit-end", (event) => {
    const frame = event.target.closest?.('[data-qna-frame="stage"]')

    if (frame && !event.detail.success && event.detail.fetchResponse?.statusCode !== 422) {
        submittedStageFocus.delete(frame)
    }
})

function preserveStageFocus(render, event) {
    const active = document.activeElement
    const frame = event.target.matches("turbo-stream")
        ? document.getElementById(event.target.getAttribute("target"))
        : event.target
    const id = submittedStageFocus.get(frame)
        || (active?.closest('[data-qna-frame="stage"]') === frame ? active.id : null)

    submittedStageFocus.delete(frame)

    return async (...args) => {
        await render(...args)

        if (id && (!document.activeElement || document.activeElement === document.body || document.activeElement === active)) {
            document.getElementById(id)?.focus({ preventScroll: true })
        }
    }
}

document.addEventListener("turbo:before-stream-render", (event) => {
    event.detail.render = preserveStageFocus(event.detail.render, event)
})

document.addEventListener("turbo:before-frame-render", (event) => {
    event.detail.render = preserveStageFocus(event.detail.render, event)
})

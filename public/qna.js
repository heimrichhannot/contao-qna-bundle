if (!window.Turbo) {
    const Turbo = await import("./turbo.es2017-esm.js?v=b9d35d123a07")

    Turbo.session.drive = false
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

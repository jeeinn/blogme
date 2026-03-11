(function (global) {
    function getStorage() {
        try {
            return global.localStorage
        } catch (_) {
            return null
        }
    }

    function buildSnapshot(payload) {
        const comparable = { ...(payload || {}) }
        delete comparable.updated_at
        return JSON.stringify(comparable)
    }

    function removeKeys(keys) {
        const storage = getStorage()
        if (!storage) {
            return
        }
        keys.forEach(key => {
            if (!key) {
                return
            }
            try {
                storage.removeItem(key)
            } catch (_) {}
        })
    }

    function cleanUrlParams(params) {
        if (!global.history || !global.history.replaceState) {
            return
        }
        const search = new URLSearchParams(global.location.search)
        let changed = false
        params.forEach(param => {
            if (search.has(param)) {
                search.delete(param)
                changed = true
            }
        })
        if (!changed) {
            return
        }
        const query = search.toString()
        const cleanUrl = global.location.pathname + (query ? "?" + query : "") + global.location.hash
        global.history.replaceState(null, "", cleanUrl)
    }

    function loadDraft(storageKey) {
        const storage = getStorage()
        if (!storage) {
            return null
        }
        try {
            const raw = storage.getItem(storageKey)
            return raw ? JSON.parse(raw) : null
        } catch (_) {
            return null
        }
    }

    function preparePage(options) {
        const settings = options || {}
        const search = new URLSearchParams(global.location.search)
        if (typeof settings.shouldClear === "function" && settings.shouldClear(search)) {
            removeKeys([settings.storageKey, ...(settings.clearKeys || [])])
            cleanUrlParams(settings.urlParamsToStrip || [])
            return null
        }

        const draft = loadDraft(settings.storageKey)
        if (!draft) {
            return null
        }

        if (typeof settings.buildCurrentPayload === "function") {
            const currentSnapshot = buildSnapshot(settings.buildCurrentPayload())
            const draftSnapshot = buildSnapshot(draft)
            if (currentSnapshot === draftSnapshot) {
                return null
            }
        }

        const confirmed = global.confirm(settings.confirmMessage || "检测到未保存草稿，是否恢复？")
        if (!confirmed) {
            return null
        }

        if (typeof settings.applyDraftToDom === "function") {
            settings.applyDraftToDom(draft)
        }

        return draft
    }

    function createManager(options) {
        const settings = options || {}
        const storage = getStorage()

        return {
            ready: false,
            restored: false,
            intervalId: null,
            bootstrapId: null,
            bootstrapAttempts: 0,
            bootstrapStableCount: 0,
            bootstrapLastValue: null,
            guardBound: false,
            lastSavedSnapshot: "",
            baselineSnapshot: "",

            buildPayload() {
                return settings.buildPayload()
            },

            setBaseline() {
                this.baselineSnapshot = buildSnapshot(this.buildPayload())
            },

            clear() {
                if (storage) {
                    try {
                        storage.removeItem(settings.storageKey)
                    } catch (_) {}
                }
                this.lastSavedSnapshot = ""
                this.restored = false
                this.setBaseline()
            },

            save(force) {
                if (!storage) {
                    return
                }
                if (!this.ready && !force) {
                    return
                }

                const payload = this.buildPayload()
                const snapshot = buildSnapshot(payload)
                const changed = this.restored || this.baselineSnapshot === "" || snapshot !== this.baselineSnapshot

                if (!changed) {
                    if (this.lastSavedSnapshot !== "") {
                        try {
                            storage.removeItem(settings.storageKey)
                        } catch (_) {}
                        this.lastSavedSnapshot = ""
                    }
                    return
                }

                if (snapshot === this.lastSavedSnapshot) {
                    return
                }

                try {
                    storage.setItem(settings.storageKey, JSON.stringify(payload))
                    this.lastSavedSnapshot = snapshot
                } catch (_) {}
            },

            bootstrap() {
                if (this.ready) {
                    return
                }

                const currentValue = typeof settings.getStableValue === "function"
                    ? settings.getStableValue()
                    : ""

                if (this.bootstrapLastValue === null) {
                    this.bootstrapLastValue = currentValue
                } else if (currentValue === this.bootstrapLastValue) {
                    this.bootstrapStableCount += 1
                } else {
                    this.bootstrapStableCount = 0
                    this.bootstrapLastValue = currentValue
                }

                this.bootstrapAttempts += 1
                if (this.bootstrapStableCount >= 2 || this.bootstrapAttempts >= 20) {
                    this.setBaseline()
                    this.ready = true
                    if (this.bootstrapId) {
                        global.clearInterval(this.bootstrapId)
                        this.bootstrapId = null
                    }
                }
            },

            start() {
                if (!this.guardBound) {
                    this.guardBound = true
                    global.addEventListener("pagehide", () => this.save(true))
                    document.addEventListener("visibilitychange", () => {
                        if (document.visibilityState === "hidden") {
                            this.save(true)
                        }
                    })
                }

                if (!this.bootstrapId) {
                    this.bootstrapId = global.setInterval(() => this.bootstrap(), 500)
                    this.bootstrap()
                }

                if (!this.intervalId) {
                    this.intervalId = global.setInterval(() => this.save(false), settings.intervalMs || 5000)
                }
            },
        }
    }

    global.BlogmePostDraft = {
        preparePage,
        createManager,
        buildSnapshot,
    }
})(window)

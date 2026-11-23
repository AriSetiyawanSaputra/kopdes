import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Composable for POS 15-minute idle auto-lock & manual quick lock (F12) (PRD 6.5 & FR-04).
 * Listens to mousemove, keydown, click, and touchstart events throttled every 5 seconds.
 * Triggers POST /pos/lock when idle timeout expires.
 *
 * @param {Object} options
 * @param {number} [options.timeoutMs=900000] - Idle timeout in milliseconds (default 15 minutes)
 * @param {number} [options.throttleMs=5000] - Activity listener throttle interval in milliseconds (default 5 seconds)
 * @param {import('vue').Ref<boolean>} [options.isLocked] - Reactive ref tracking lock state
 * @param {Function} [options.onLocked] - Callback invoked when POS locks
 */
export function useIdleLock(options = {}) {
    const timeoutMs = options.timeoutMs ?? 900000;
    const throttleMs = options.throttleMs ?? 5000;
    const isLocked = options.isLocked ?? ref(false);
    const onLocked = options.onLocked ?? (() => {});

    const lastActivityAt = ref(Date.now());
    let idleTimer = null;
    let lastThrottledAt = 0;

    const activityEvents = ['mousemove', 'keydown', 'click', 'touchstart'];

    const clearIdleTimer = () => {
        if (idleTimer !== null) {
            clearTimeout(idleTimer);
            idleTimer = null;
        }
    };

    const lockPosSession = async () => {
        clearIdleTimer();
        isLocked.value = true;
        onLocked();

        try {
            if (window.axios) {
                await window.axios.post(route('pos.lock'));
            } else {
                await window.fetch(route('pos.lock'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
            }
        } catch {
            // Keep UI locked even if network request had a transient issue
        }
    };

    const scheduleIdleTimer = () => {
        clearIdleTimer();

        if (isLocked.value) {
            return;
        }

        idleTimer = setTimeout(() => {
            if (!isLocked.value) {
                lockPosSession();
            }
        }, timeoutMs);
    };

    const recordActivity = () => {
        if (isLocked.value) {
            return;
        }

        const now = Date.now();
        if (now - lastThrottledAt < throttleMs) {
            return;
        }

        lastThrottledAt = now;
        lastActivityAt.value = now;
        scheduleIdleTimer();
    };

    const resetIdleTimer = () => {
        lastThrottledAt = Date.now();
        lastActivityAt.value = lastThrottledAt;
        scheduleIdleTimer();
    };

    onMounted(() => {
        activityEvents.forEach((eventName) => {
            window.addEventListener(eventName, recordActivity, { passive: true });
        });

        scheduleIdleTimer();
    });

    onBeforeUnmount(() => {
        activityEvents.forEach((eventName) => {
            window.removeEventListener(eventName, recordActivity);
        });

        clearIdleTimer();
    });

    return {
        isLocked,
        lastActivityAt,
        lockPosSession,
        resetIdleTimer,
    };
}

export default useIdleLock;

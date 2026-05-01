<script setup>
    import { ref, onMounted } from 'vue';
    import { store } from '../store.js';

    import LoadingSpinner from '../components/LoadingSpinner.vue';
    import Widget from '../widget/Widget.vue';

    const cw_theme = window.cw_theme;

    const signals = ref([]);
    const loading = ref(true);
    const error = ref(null);
    const lastSignalAt = ref(null);
    const serverTime = ref(null);

    function loadSignals() {
        loading.value = true;
        error.value = null;
        jQuery.ajax({
            type: 'GET',
            url: '/api/agoraiq/signals?limit=20',
            success: function (data) {
                signals.value = (data && data.signals) ? data.signals : [];
                lastSignalAt.value = data ? data.last_signal_at : null;
                serverTime.value = data ? data.server_time : null;
                loading.value = false;
            },
            error: function (xhr) {
                error.value = xhr.status === 401
                    ? 'Please log in to view AgoraIQ signals.'
                    : 'Could not load AgoraIQ signals right now.';
                loading.value = false;
            },
        });
    }

    // Returns { label, level } where level is 'fresh' | 'warn' | 'stale'.
    // Thresholds match operational reality: scanner cycles ~every 10s, so
    // anything past 15min is unusual; past 60min the feed is effectively
    // dead and users should be told visibly.
    function staleness() {
        if (!lastSignalAt.value) return null;
        const last = new Date(lastSignalAt.value).getTime();
        const now = serverTime.value ? new Date(serverTime.value).getTime() : Date.now();
        if (isNaN(last) || isNaN(now)) return null;
        const ageMs = Math.max(0, now - last);
        const mins = Math.floor(ageMs / 60000);
        let level = 'fresh';
        if (mins >= 60) level = 'stale';
        else if (mins >= 15) level = 'warn';
        let label;
        if (mins < 1) label = 'Last signal: just now';
        else if (mins < 60) label = `Last signal: ${mins}m ago`;
        else if (mins < 1440) label = `Last signal: ${Math.floor(mins / 60)}h ago`;
        else label = `Last signal: ${Math.floor(mins / 1440)}d ago`;
        return { label, level };
    }

    function statusClass(status) {
        if (status === 'OPEN') return 'sig-status sig-open';
        if (status === 'TP1' || status === 'TP2' || status === 'TP3') return 'sig-status sig-win';
        if (status === 'SL') return 'sig-status sig-loss';
        return 'sig-status sig-neutral';
    }

    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleString();
    }

    onMounted(() => {
        if (store.userLoggedIn) {
            loadSignals();
        } else {
            loading.value = false;
        }
    });
</script>

<template>
    <widget style="text-align:center;" :title="'AgoraIQ Signals'">

        <div id="agoraiq-signals">

            <div class="container">

                <span v-if="store.userLoggedIn">

                    <div style="margin-top:20px;line-height:160%;">
                        Live trading signals from
                        <b>AgoraIQ</b>. Read-only feed — entry, stop, and
                        targets are reserved for AgoraIQ subscribers.
                    </div>

                    <div v-if="!loading && !error && staleness()"
                         :class="'sig-freshness sig-freshness-' + staleness().level"
                         style="margin-top:14px;">
                        <span v-if="staleness().level === 'stale'">⚠ Feed appears offline. </span>
                        <span v-else-if="staleness().level === 'warn'">Feed is quiet. </span>
                        {{ staleness().label }}
                    </div>

                    <div style="height:20px;"></div>

                    <LoadingSpinner v-if="loading" :theme='cw_theme' />

                    <div v-if="error && !loading" class="content" style="margin-top:30px;">
                        {{ error }}
                    </div>

                    <div v-if="!loading && !error && signals.length === 0" class="content" style="margin-top:30px;">
                        No signals yet. Check back shortly.
                    </div>

                    <table v-if="!loading && !error && signals.length > 0"
                           class="agoraiq-signals-table"
                           style="width:100%;margin-top:10px;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:8px 6px;">Symbol</th>
                                <th style="text-align:left;padding:8px 6px;">Side</th>
                                <th style="text-align:left;padding:8px 6px;">Status</th>
                                <th style="text-align:right;padding:8px 6px;">Confidence</th>
                                <th style="text-align:right;padding:8px 6px;">Result</th>
                                <th style="text-align:right;padding:8px 6px;">When</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sig in signals" :key="sig.id" style="border-top:1px solid rgba(0,0,0,0.08);">
                                <td style="text-align:left;padding:8px 6px;"><b>{{ sig.symbol }}</b></td>
                                <td style="text-align:left;padding:8px 6px;">{{ sig.direction }}</td>
                                <td style="text-align:left;padding:8px 6px;">
                                    <span :class="statusClass(sig.status)">{{ sig.status }}</span>
                                </td>
                                <td style="text-align:right;padding:8px 6px;">
                                    <span v-if="sig.confidence != null">{{ Math.round(sig.confidence) }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td style="text-align:right;padding:8px 6px;">
                                    <span v-if="sig.result != null">{{ sig.result.toFixed(2) }}%</span>
                                    <span v-else>—</span>
                                </td>
                                <td style="text-align:right;padding:8px 6px;font-size:12px;">{{ formatTime(sig.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>

                </span>
                <span v-else>

                    <div style="margin-top:45px;padding-left:20px;padding-right:20px;">
                        <h2>AgoraIQ Signals</h2>
                        <div style="height:5px;"></div>
                        <p style="line-height:160%;">
                            AgoraIQ trading signals appear here once you are
                            logged in.
                        </p>
                        <p style="line-height:160%;">
                            <router-link to="/account/login" class="blacklink"><b>Log in</b></router-link>
                            or
                            <router-link to="/account/register" class="blacklink"><b>create an account</b></router-link>
                            to continue.
                        </p>
                    </div>

                </span>

            </div>

        </div>

    </widget>
</template>

<style scoped>
    .sig-status {
        display:inline-block;
        padding:2px 8px;
        border-radius:10px;
        font-size:12px;
        font-weight:600;
    }
    .sig-open    { background:#e7f0ff; color:#1a4fbf; }
    .sig-win     { background:#e6f7ec; color:#1d7a3a; }
    .sig-loss    { background:#fdecec; color:#a32424; }
    .sig-neutral { background:#eee;    color:#444;    }

    .sig-freshness {
        display:inline-block;
        padding:6px 12px;
        border-radius:6px;
        font-size:13px;
    }
    .sig-freshness-fresh { background:#e6f7ec; color:#1d7a3a; }
    .sig-freshness-warn  { background:#fff7e0; color:#8a6100; }
    .sig-freshness-stale { background:#fdecec; color:#a32424; font-weight:600; }
</style>

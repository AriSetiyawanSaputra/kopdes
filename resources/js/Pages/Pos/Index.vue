<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CloseShiftModal from '@/Pages/Pos/Partials/CloseShiftModal.vue';
import HardwareSimulatorPopover from '@/Pages/Pos/Partials/HardwareSimulatorPopover.vue';
import MemberLookupModal from '@/Pages/Pos/Partials/MemberLookupModal.vue';
import OpenShiftModal from '@/Pages/Pos/Partials/OpenShiftModal.vue';
import PaymentModal from '@/Pages/Pos/Partials/PaymentModal.vue';
import PendingTransactionsDrawer from '@/Pages/Pos/Partials/PendingTransactionsDrawer.vue';
import PosLockModal from '@/Pages/Pos/Partials/PosLockModal.vue';
import ReceiptThermal from '@/Pages/Pos/Partials/ReceiptThermal.vue';
import ShiftCashMovementModal from '@/Pages/Pos/Partials/ShiftCashMovementModal.vue';
import ShiftClosingReceipt from '@/Pages/Pos/Partials/ShiftClosingReceipt.vue';
import { useIdleLock } from '@/Composables/useIdleLock';
import { generateUuidV4 } from '@/Utils/uuid';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    branch: {
        type: Object,
        required: true,
    },
    cashier: {
        type: Object,
        required: true,
    },
    activeShift: {
        type: Object,
        default: null,
    },
    pendingTransactionsForShift: {
        type: Array,
        default: () => [],
    },
    pendingCountForShift: {
        type: Number,
        default: 0,
    },
    products: {
        type: Array,
        default: () => [],
    },
    initialPendingTransactions: {
        type: Array,
        default: () => [],
    },
    initialTodayTransactions: {
        type: Array,
        default: () => [],
    },
    posLocked: {
        type: Boolean,
        default: false,
    },
    midtrans: {
        type: Object,
        default: () => ({}),
    },
});

const STORAGE_PAPER_WIDTH_KEY = 'kopdes_thermal_paper_width';

const currentCashier = ref({ ...props.cashier });
const currentShift = ref(props.activeShift ? { ...props.activeShift } : null);
const shiftPendingTransactions = ref([...props.pendingTransactionsForShift]);
const catalogProducts = ref(props.products.map((p) => ({ ...p })));
const cartItems = ref([]);
const selectedMember = ref(null);
const idempotencyKey = ref(null);

const barcodeQuery = ref('');
const catalogSearch = ref('');
const barcodeInputRef = ref(null);

const activeModal = ref(props.posLocked ? 'lock' : (!props.activeShift ? 'open-shift' : null));
const isLocked = ref(Boolean(props.posLocked));
const isScanningBarcode = ref(false);
const isSubmitting = ref(false);
const processingTxId = ref(null);

const alertBanner = ref(null);
const paymentError = ref('');

const pendingTransactions = ref([...props.initialPendingTransactions]);
const todayTransactions = ref([...props.initialTodayTransactions]);
const activeReceiptTransaction = ref(null);
const activeShiftClosingReceipt = ref(null);
const page = usePage();
const isDemoMode = computed(() => Boolean(page.props.app?.demo_mode));
const defaultPaperWidth =
    page.props.app_settings?.default_receipt_paper === '80mm' ? '80mm' : '58mm';
const paperWidth = ref(defaultPaperWidth);

const { lockPosSession, resetIdleTimer } = useIdleLock({
    timeoutMs: 900000,
    throttleMs: 5000,
    isLocked,
    onLocked: () => {
        activeModal.value = 'lock';
    },
});

const filteredCatalog = computed(() => {
    const q = catalogSearch.value.trim().toLowerCase();
    if (!q) {
        return catalogProducts.value;
    }

    return catalogProducts.value.filter((p) => {
        return (
            p.name.toLowerCase().includes(q) ||
            p.sku.toLowerCase().includes(q) ||
            p.barcode.toLowerCase().includes(q)
        );
    });
});

const totalItemsCount = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + Number(item.qty || 0), 0);
});

const isItemMemberPriceApplied = (item) => {
    return (
        Boolean(selectedMember.value) &&
        item.member_price !== null &&
        item.member_price !== undefined
    );
};

const getEffectiveUnitPrice = (item) => {
    if (isItemMemberPriceApplied(item)) {
        return Number(item.member_price);
    }

    return Number(item.selling_price || 0);
};

const getItemMemberSavings = (item) => {
    const regular = Number(item.selling_price || 0);
    const effective = getEffectiveUnitPrice(item);
    return Math.max(0, (regular - effective) * Number(item.qty || 0));
};

const regularSubtotal = computed(() => {
    return cartItems.value.reduce(
        (sum, item) => sum + Number(item.selling_price || 0) * Number(item.qty || 0),
        0,
    );
});

const grandTotal = computed(() => {
    return cartItems.value.reduce(
        (sum, item) => sum + getEffectiveUnitPrice(item) * Number(item.qty || 0),
        0,
    );
});

const totalMemberSavings = computed(() => {
    return Math.max(0, regularSubtotal.value - grandTotal.value);
});

const formatRupiah = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
};

const showNotification = (type, message) => {
    alertBanner.value = { type, message };
};

/**
 * Maintain permanent barcode input auto-focus when activeModal === null
 * and cursor is not actively inside quantity/search input fields (FR-10 & PRD 6.4).
 */
const focusBarcodeInputIfIdle = () => {
    if (activeModal.value !== null || isLocked.value) {
        return;
    }

    nextTick(() => {
        const activeEl = document.activeElement;
        const isEditingOtherField =
            activeEl &&
            (activeEl.dataset?.ignoreBarcodeFocus === 'true' ||
                activeEl.tagName === 'SELECT' ||
                activeEl.tagName === 'TEXTAREA');

        if (!isEditingOtherField && barcodeInputRef.value) {
            barcodeInputRef.value.focus();
        }
    });
};

watch(activeModal, (newModal) => {
    if (newModal === null && !isLocked.value) {
        focusBarcodeInputIfIdle();
    }
});

/**
 * Ensure idempotency_key (UUID v4) is generated when cart has at least 1 item,
 * regenerated whenever cart composition/qty or selectedMember changes before checkout (PRD_PHASE2 6.3 #5),
 * and cleared when cart is empty or checkout completes.
 */
const ensureIdempotencyKey = () => {
    if (cartItems.value.length > 0 && !idempotencyKey.value) {
        idempotencyKey.value = generateUuidV4();
    } else if (cartItems.value.length === 0) {
        idempotencyKey.value = null;
    }
};

const regenerateIdempotencyKey = () => {
    if (cartItems.value.length > 0) {
        idempotencyKey.value = generateUuidV4();
    } else {
        idempotencyKey.value = null;
    }
};

watch(
    () => selectedMember.value?.id ?? null,
    () => {
        regenerateIdempotencyKey();
    },
);

const addProductToCart = (productData) => {
    const availableStock = Number(productData.available_stock ?? 0);
    const parsedMemberPrice =
        productData.member_price !== null && productData.member_price !== undefined
            ? Number(productData.member_price)
            : null;

    if (availableStock < 1) {
        showNotification(
            'error',
            `Stok tersedia untuk produk "${productData.name}" habis (Tersedia: 0 ${productData.unit}).`,
        );
        focusBarcodeInputIfIdle();
        return;
    }

    const existing = cartItems.value.find((item) => item.product_id === productData.id);

    if (existing) {
        if (existing.qty + 1 > availableStock) {
            showNotification(
                'warning',
                `Kuantitas "${productData.name}" di keranjang sudah mencapai batas stok tersedia (${availableStock} ${productData.unit}).`,
            );
            focusBarcodeInputIfIdle();
            return;
        }
        existing.qty += 1;
        existing.available_stock = availableStock;
        existing.selling_price = Number(productData.selling_price);
        existing.member_price = parsedMemberPrice;
    } else {
        cartItems.value.push({
            product_id: productData.id,
            sku: productData.sku,
            barcode: productData.barcode,
            name: productData.name,
            unit: productData.unit,
            selling_price: Number(productData.selling_price),
            member_price: parsedMemberPrice,
            available_stock: availableStock,
            qty: 1,
        });
    }

    // Update catalog cache with latest stock & pricing info
    const catalogIdx = catalogProducts.value.findIndex((p) => p.id === productData.id);
    if (catalogIdx !== -1) {
        catalogProducts.value[catalogIdx].selling_price = Number(productData.selling_price);
        catalogProducts.value[catalogIdx].member_price = parsedMemberPrice;
        catalogProducts.value[catalogIdx].available_stock = availableStock;
        catalogProducts.value[catalogIdx].quantity = Number(
            productData.quantity ?? catalogProducts.value[catalogIdx].quantity,
        );
        catalogProducts.value[catalogIdx].reserved_qty = Number(
            productData.reserved_qty ?? catalogProducts.value[catalogIdx].reserved_qty,
        );
    }

    regenerateIdempotencyKey();
    alertBanner.value = null;
    focusBarcodeInputIfIdle();
};

const handleBarcodeSubmit = async () => {
    if (activeModal.value !== null || isLocked.value || isScanningBarcode.value) {
        return;
    }

    const code = barcodeQuery.value.trim();
    if (!code) {
        focusBarcodeInputIfIdle();
        return;
    }

    isScanningBarcode.value = true;

    try {
        const response = await window.axios.get(
            route('pos.products.barcode', { barcode: code }),
        );
        barcodeQuery.value = '';
        addProductToCart(response.data);
    } catch (error) {
        const msg =
            error?.response?.data?.message ||
            'Barcode tidak ditemukan. Jika Anda memindai Kartu Member, tekan F2 terlebih dahulu.';
        showNotification('error', msg);
        barcodeQuery.value = '';
    } finally {
        isScanningBarcode.value = false;
        focusBarcodeInputIfIdle();
    }
};

const simulateBarcodeScan = async (barcode) => {
    if (!barcode || isLocked.value || !currentShift.value) {
        return;
    }
    barcodeQuery.value = barcode;
    await handleBarcodeSubmit();
};

const simulateMemberTap = async (cardUid) => {
    if (!cardUid || isLocked.value || !currentShift.value) {
        return;
    }
    try {
        const response = await window.axios.get(route('pos.members.lookup'), {
            params: { card_uid: cardUid },
        });
        selectedMember.value = response.data;
        showNotification(
            'success',
            `[Simulasi RFID] Member tertaut: ${response.data.name} — ${response.data.home_branch_name}`,
        );
    } catch (error) {
        showNotification(
            'error',
            error?.response?.data?.message || 'Gagal menautkan kartu member simulasi.',
        );
    }
};

const updateItemQty = (item, newQty) => {
    const parsed = parseInt(newQty, 10);

    if (Number.isNaN(parsed) || parsed < 1) {
        removeItemFromCart(item.product_id);
        return;
    }

    if (parsed > item.available_stock) {
        item.qty = item.available_stock;
        showNotification(
            'warning',
            `Maksimal stok tersedia untuk "${item.name}" adalah ${item.available_stock} ${item.unit}.`,
        );
    } else {
        item.qty = parsed;
    }

    regenerateIdempotencyKey();
};

const removeItemFromCart = (productId) => {
    cartItems.value = cartItems.value.filter((i) => i.product_id !== productId);
    regenerateIdempotencyKey();
    focusBarcodeInputIfIdle();
};

const clearCartAfterSuccess = () => {
    cartItems.value = [];
    selectedMember.value = null;
    idempotencyKey.value = null;
    paymentError.value = '';
};

const resetCartManually = () => {
    if (cartItems.value.length === 0) {
        return;
    }

    if (!window.confirm('Kosongkan seluruh item di keranjang belanja saat ini?')) {
        return;
    }

    clearCartAfterSuccess();
    focusBarcodeInputIfIdle();
};

const changePaperWidth = (width) => {
    paperWidth.value = width;
    try {
        window.localStorage.setItem(STORAGE_PAPER_WIDTH_KEY, width);
    } catch {
        // Ignore storage errors
    }
};

const triggerThermalPrint = (transactionData) => {
    activeShiftClosingReceipt.value = null;
    activeReceiptTransaction.value = transactionData;
    nextTick(() => {
        window.print();
        focusBarcodeInputIfIdle();
    });
};

const triggerShiftClosingPrint = (closedShiftData) => {
    activeReceiptTransaction.value = null;
    activeShiftClosingReceipt.value = closedShiftData;
    nextTick(() => {
        window.print();
    });
};

const refreshTransactionsLists = async () => {
    try {
        const requests = [
            window.axios.get(route('pos.transactions.pending')),
            window.axios.get(route('pos.transactions.today')),
        ];
        if (currentShift.value?.id) {
            requests.push(window.axios.get(route('pos.shifts.summary', currentShift.value.id)));
        }
        const [pendingRes, todayRes, shiftRes] = await Promise.all(requests);

        pendingTransactions.value = pendingRes.data.transactions || [];
        todayTransactions.value = todayRes.data.transactions || [];
        if (shiftRes?.data) {
            shiftPendingTransactions.value = shiftRes.data.pending_transactions_for_shift || [];
        }
    } catch {
        // Keep existing lists on error
    }
};

const deductLocalCatalogStock = (items, isReservationOnly = false) => {
    items.forEach((cartItem) => {
        const prod = catalogProducts.value.find((p) => p.id === cartItem.product_id);
        if (prod) {
            const qty = Number(cartItem.qty || 0);
            if (isReservationOnly) {
                prod.reserved_qty = Number(prod.reserved_qty || 0) + qty;
            } else {
                prod.quantity = Math.max(0, Number(prod.quantity || 0) - qty);
            }
            prod.available_stock = Math.max(
                0,
                Number(prod.quantity || 0) - Number(prod.reserved_qty || 0),
            );
        }
    });
};

const openSnapPopup = (transactionData) => {
    if (!transactionData?.snap_token) {
        showNotification(
            'warning',
            `Transaksi ${transactionData?.invoice_number} tersimpan sebagai pending. Silakan cek panel Transaksi Pending Cabang.`,
        );
        return;
    }

    if (typeof window.snap !== 'undefined' && typeof window.snap.pay === 'function') {
        window.snap.pay(transactionData.snap_token, {
            onSuccess: async () => {
                await handleCheckTransactionStatus(transactionData, true);
            },
            onPending: async () => {
                await refreshTransactionsLists();
                showNotification(
                    'info',
                    `Pembayaran ${transactionData.invoice_number} masih menunggu penyelesaian. Pantau di panel Transaksi Pending Cabang.`,
                );
            },
            onError: async () => {
                await handleCheckTransactionStatus(transactionData, false);
            },
            onClose: async () => {
                await refreshTransactionsLists();
                showNotification(
                    'warning',
                    `Pop-up pembayaran ditutup. Transaksi ${transactionData.invoice_number} tersimpan di panel Transaksi Pending Cabang.`,
                );
            },
        });
    } else {
        activeModal.value = 'drawer';
        showNotification(
            'info',
            `Transaksi Midtrans ${transactionData.invoice_number} dibuat (Snap Token: ${transactionData.snap_token}). Gunakan tombol Cek Status Pembayaran setelah pelanggan membayar.`,
        );
    }
};

const handleCheckoutSubmit = async ({ payment_method, cash_received }) => {
    if (isSubmitting.value || cartItems.value.length === 0) {
        return;
    }

    ensureIdempotencyKey();
    isSubmitting.value = true;
    paymentError.value = '';

    const cartSnapshot = cartItems.value.map((i) => ({
        product_id: i.product_id,
        qty: i.qty,
    }));

    try {
        const response = await window.axios.post(route('pos.checkout'), {
            idempotency_key: idempotencyKey.value,
            payment_method,
            member_id: selectedMember.value?.id ?? null,
            cash_received: payment_method === 'cash' ? cash_received : null,
            items: cartSnapshot,
        });

        const tx = response.data.transaction || response.data;
        activeModal.value = null;

        if (payment_method === 'cash') {
            deductLocalCatalogStock(cartSnapshot, false);
            clearCartAfterSuccess();
            await refreshTransactionsLists();
            showNotification(
                'success',
                `Transaksi Tunai ${tx.invoice_number} lunas! Mencetak struk thermal...`,
            );
            triggerThermalPrint(tx);
        } else {
            deductLocalCatalogStock(cartSnapshot, true);
            clearCartAfterSuccess();
            await refreshTransactionsLists();
            openSnapPopup(tx);
        }
    } catch (error) {
        const data = error?.response?.data || {};
        const firstValidationError = data?.errors
            ? Object.values(data.errors).flat()[0]
            : null;
        paymentError.value =
            firstValidationError ||
            data?.message ||
            'Gagal memproses transaksi checkout. Silakan periksa ketersediaan stok atau koneksi.';
    } finally {
        isSubmitting.value = false;
    }
};

const handleCheckTransactionStatus = async (tx, autoPrintIfPaid = true) => {
    processingTxId.value = tx.id;
    try {
        const response = await window.axios.post(
            route('pos.transactions.check-status', tx.id),
        );
        const updatedTx = response.data.transaction || response.data;
        await refreshTransactionsLists();

        if (updatedTx.payment_status === 'paid') {
            showNotification(
                'success',
                `Pembayaran Midtrans ${updatedTx.invoice_number} TERKONFIRMASI LUNAS!`,
            );
            if (autoPrintIfPaid) {
                triggerThermalPrint(updatedTx);
            }
        } else {
            showNotification(
                'info',
                response.data.message ||
                    `Status transaksi ${updatedTx.invoice_number}: ${updatedTx.payment_status}`,
            );
        }
    } catch (error) {
        showNotification(
            'error',
            error?.response?.data?.message || 'Gagal memeriksa status transaksi ke Midtrans.',
        );
    } finally {
        processingTxId.value = null;
    }
};

const handleCancelTransaction = async (tx) => {
    if (
        !window.confirm(
            `Batalkan transaksi Midtrans "${tx.invoice_number}" dan lepaskan stok yang ditahan?`,
        )
    ) {
        return;
    }

    processingTxId.value = tx.id;
    try {
        const response = await window.axios.post(
            route('pos.transactions.cancel', tx.id),
        );
        await refreshTransactionsLists();
        showNotification(
            'info',
            response.data.message || `Transaksi ${tx.invoice_number} dibatalkan.`,
        );
        router.reload({ only: ['products'] });
    } catch (error) {
        showNotification(
            'error',
            error?.response?.data?.message || 'Gagal membatalkan transaksi.',
        );
    } finally {
        processingTxId.value = null;
    }
};

const handlePosUnlocked = ({
    switchedCashier,
    cashier,
    activeShift,
    pendingTransactionsForShift,
    message,
}) => {
    isLocked.value = false;
    resetIdleTimer();

    if (switchedCashier) {
        currentCashier.value = { ...cashier };
        currentShift.value = activeShift ? { ...activeShift } : null;
        shiftPendingTransactions.value = [...(pendingTransactionsForShift || [])];
        clearCartAfterSuccess();
        activeModal.value = currentShift.value ? null : 'open-shift';
        showNotification('warning', message || 'Kasir berganti, keranjang telah direset.');
    } else {
        if (activeShift !== undefined) {
            currentShift.value = activeShift ? { ...activeShift } : null;
            shiftPendingTransactions.value = [...(pendingTransactionsForShift || [])];
        }
        activeModal.value = currentShift.value ? null : 'open-shift';
        showNotification('success', message || 'Layar POS berhasil dibuka kembali.');
    }

    if (activeModal.value === null) {
        focusBarcodeInputIfIdle();
    }
};

const handleShiftOpened = ({ shift, pendingTransactions: shiftPending, message }) => {
    currentShift.value = shift;
    shiftPendingTransactions.value = shiftPending || [];
    activeModal.value = null;
    showNotification('success', message || 'Shift kasir berhasil dibuka.');
    focusBarcodeInputIfIdle();
};

const handleCashMovementRecorded = ({ shift, message }) => {
    currentShift.value = shift;
    showNotification('success', message || 'Mutasi kas laci berhasil dicatat.');
};

const handleShiftClosed = ({ message }) => {
    currentShift.value = null;
    shiftPendingTransactions.value = [];
    clearCartAfterSuccess();
    showNotification('success', message || 'Shift kasir berhasil ditutup.');
};

const handleLogout = () => {
    if (
        cartItems.value.length > 0 &&
        !window.confirm('Masih ada item di keranjang. Yakin ingin keluar dari sesi Kasir POS?')
    ) {
        return;
    }

    router.post(route('pos.logout'));
};

const handleGlobalKeydown = (event) => {
    if (isLocked.value || !currentShift.value) {
        return;
    }

    if (event.key === 'F2') {
        event.preventDefault();
        activeModal.value = activeModal.value === 'member' ? null : 'member';
    } else if (event.key === 'F4') {
        event.preventDefault();
        activeModal.value = activeModal.value === 'cash-movement' ? null : 'cash-movement';
    } else if (event.key === 'F9') {
        event.preventDefault();
        if (cartItems.value.length > 0) {
            paymentError.value = '';
            activeModal.value = 'payment';
        }
    } else if (event.key === 'F10') {
        event.preventDefault();
        activeModal.value = activeModal.value === 'close-shift' ? null : 'close-shift';
    } else if (event.key === 'F12') {
        event.preventDefault();
        lockPosSession();
    }
};

onMounted(() => {
    try {
        const savedWidth = window.localStorage.getItem(STORAGE_PAPER_WIDTH_KEY);
        if (savedWidth === '58mm' || savedWidth === '80mm') {
            paperWidth.value = savedWidth;
        }
    } catch {
        // Ignore storage error
    }

    window.addEventListener('keydown', handleGlobalKeydown);
    focusBarcodeInputIfIdle();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <Head :title="`POS Kasir — ${branch.name}`" />

    <!-- Area Layar Utama POS (Tidak ikut tercetak saat @media print) -->
    <div
        class="no-print flex min-h-screen flex-col bg-gray-100"
        @click="focusBarcodeInputIfIdle"
    >
        <!-- Top Header POS -->
        <header class="border-b border-red-900 bg-red-800 px-4 py-3 text-white shadow">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-white px-2.5 py-1 text-sm font-black tracking-tight text-red-800">
                        POS KOPDES
                    </div>
                    <div>
                        <h1 class="text-base font-extrabold leading-tight">
                            {{ branch.name }}
                            <span class="font-mono text-xs font-normal text-red-200">
                                ({{ branch.code }})
                            </span>
                        </h1>
                        <p class="flex flex-wrap items-center gap-2 text-xs text-red-200">
                            <span>Kasir Aktif: <strong class="text-white">{{ currentCashier.name }}</strong></span>
                            <span
                                v-if="currentShift"
                                class="inline-flex items-center rounded bg-emerald-500/20 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-100 ring-1 ring-emerald-400/40"
                            >
                                Shift: {{ currentShift.shift_code }}
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded bg-amber-500/20 px-1.5 py-0.5 text-[11px] font-bold text-amber-100 ring-1 ring-amber-300/40"
                            >
                                Shift Belum Dibuka
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Toolbar Shortcut & Pengaturan Struk -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Ukuran Kertas Thermal -->
                    <div class="flex items-center rounded-md bg-red-900/80 px-2.5 py-1 text-xs">
                        <span class="me-1.5 text-red-200">Struk:</span>
                        <button
                            type="button"
                            @click.stop="changePaperWidth('58mm')"
                            class="rounded px-2 py-0.5 font-bold"
                            :class="
                                paperWidth === '58mm'
                                    ? 'bg-white text-red-800'
                                    : 'text-red-200 hover:text-white'
                            "
                        >
                            58mm
                        </button>
                        <button
                            type="button"
                            @click.stop="changePaperWidth('80mm')"
                            class="rounded px-2 py-0.5 font-bold"
                            :class="
                                paperWidth === '80mm'
                                    ? 'bg-white text-red-800'
                                    : 'text-red-200 hover:text-white'
                            "
                        >
                            80mm
                        </button>
                    </div>

                    <button
                        v-if="!currentShift"
                        type="button"
                        @click.stop="activeModal = 'open-shift'"
                        class="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-amber-400 px-3 py-1.5 text-xs font-extrabold text-gray-950 hover:bg-amber-300"
                    >
                        <span>Buka Shift</span>
                    </button>

                    <button
                        v-if="currentShift"
                        type="button"
                        @click.stop="activeModal = 'cash-movement'"
                        class="inline-flex items-center gap-1.5 rounded-md border border-red-600 bg-red-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-600"
                    >
                        <span>Kas Laci</span>
                        <kbd class="rounded bg-red-900 px-1.5 py-0.5 font-mono text-[10px]">F4</kbd>
                    </button>

                    <button
                        v-if="currentShift"
                        type="button"
                        @click.stop="activeModal = 'close-shift'"
                        class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500 bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-600"
                    >
                        <span>Tutup Shift</span>
                        <kbd class="rounded bg-emerald-900 px-1.5 py-0.5 font-mono text-[10px]">F10</kbd>
                    </button>

                    <Link
                        :href="route('products.barcode-labels')"
                        class="inline-flex items-center gap-1.5 rounded-md border border-red-600 bg-red-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-600"
                    >
                        <span>Label Barcode</span>
                    </Link>

                    <button
                        type="button"
                        @click.stop="activeModal = 'member'"
                        class="inline-flex items-center gap-1.5 rounded-md border border-red-600 bg-red-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-600"
                    >
                        <span>Tap Member</span>
                        <kbd class="rounded bg-red-900 px-1.5 py-0.5 font-mono text-[10px]">F2</kbd>
                    </button>

                    <button
                        type="button"
                        @click.stop="
                            refreshTransactionsLists();
                            activeModal = 'drawer';
                        "
                        class="inline-flex items-center gap-1.5 rounded-md border border-red-600 bg-red-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-600"
                    >
                        <span>Pending &amp; Riwayat</span>
                        <span
                            v-if="pendingTransactions.length > 0"
                            class="rounded-full bg-amber-400 px-1.5 py-0.2 font-mono text-[11px] font-black text-gray-900"
                        >
                            {{ pendingTransactions.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click.stop="lockPosSession"
                        class="inline-flex items-center gap-1.5 rounded-md border border-amber-400 bg-amber-500 px-3 py-1.5 text-xs font-bold text-gray-950 hover:bg-amber-400"
                    >
                        <span>Kunci Layar</span>
                        <kbd class="rounded bg-amber-700 px-1.5 py-0.5 font-mono text-[10px] text-white">F12</kbd>
                    </button>

                    <button
                        type="button"
                        @click.stop="handleLogout"
                        class="rounded-md border border-red-500 bg-red-950/60 px-3 py-1.5 text-xs font-bold text-red-100 hover:bg-red-950"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <!-- Banner Notifikasi / Error Barcode -->
        <div v-if="alertBanner" class="mx-auto mt-3 w-full max-w-7xl px-4">
            <div
                class="flex items-center justify-between rounded-lg border px-4 py-2.5 text-xs font-semibold shadow-sm"
                :class="{
                    'border-red-300 bg-red-50 text-red-800': alertBanner.type === 'error',
                    'border-amber-300 bg-amber-50 text-amber-800': alertBanner.type === 'warning',
                    'border-green-300 bg-green-50 text-green-800': alertBanner.type === 'success',
                    'border-blue-300 bg-blue-50 text-blue-800': alertBanner.type === 'info',
                }"
            >
                <span>{{ alertBanner.message }}</span>
                <button
                    type="button"
                    @click.stop="alertBanner = null"
                    class="ms-4 font-bold underline"
                >
                    Tutup
                </button>
            </div>
        </div>

        <!-- Workspace Utama: Kiri (Scanner & Katalog) | Kanan (Member & Keranjang) -->
        <main class="mx-auto grid w-full max-w-7xl flex-1 grid-cols-1 gap-4 p-4 lg:grid-cols-12">
            <!-- Panel Kiri: Input Scanner Barcode Terisolasi + Katalog Cepat -->
            <div class="flex flex-col gap-4 lg:col-span-7">
                <!-- Box Scanner Barcode -->
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <form @submit.prevent="handleBarcodeSubmit">
                        <div class="flex items-center justify-between">
                            <label
                                for="pos-barcode-scanner-input"
                                class="text-xs font-extrabold uppercase tracking-wider text-gray-700"
                            >
                                Scan Barcode Produk (USB Barcode Scanner — Auto-Focus)
                            </label>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="
                                    activeModal === null
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-500'
                                "
                            >
                                {{ activeModal === null ? 'Scanner Siap' : 'Scanner Nonaktif (Modal Aktif)' }}
                            </span>
                        </div>

                        <div class="mt-2 flex gap-2">
                            <input
                                id="pos-barcode-scanner-input"
                                ref="barcodeInputRef"
                                v-model="barcodeQuery"
                                type="text"
                                autocomplete="off"
                                :disabled="activeModal !== null || isLocked"
                                class="block w-full rounded-lg border-gray-300 font-mono text-base font-bold text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"
                                placeholder="Pindai barcode produk atau ketik kode barcode lalu tekan Enter..."
                            />
                            <button
                                type="submit"
                                :disabled="activeModal !== null || isScanningBarcode"
                                class="rounded-lg bg-red-700 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-red-800 disabled:opacity-50"
                            >
                                Cari
                            </button>
                        </div>
                        <p class="mt-1.5 text-[11px] text-gray-500">
                            Jangan menempelkan Kartu RFID Member di kolom ini. Tekan
                            <kbd class="rounded border bg-gray-100 px-1 font-mono font-bold">F2</kbd>
                            untuk memindai Kartu Member.
                        </p>
                    </form>
                </div>

                <!-- Daftar Produk Cabang -->
                <div class="flex flex-1 flex-col rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-col justify-between gap-2 border-b border-gray-200 pb-3 sm:flex-row sm:items-center">
                        <h2 class="text-sm font-extrabold text-gray-900">
                            Katalog Produk &amp; Ketersediaan Stok Cabang
                        </h2>
                        <input
                            v-model="catalogSearch"
                            data-ignore-barcode-focus="true"
                            type="text"
                            placeholder="Filter nama / SKU..."
                            class="rounded-md border-gray-300 text-xs shadow-sm focus:border-red-600 focus:ring-red-600 sm:w-56"
                        />
                    </div>

                    <div class="mt-3 grid max-h-[55vh] grid-cols-1 gap-2.5 overflow-y-auto sm:grid-cols-2">
                        <button
                            v-for="prod in filteredCatalog"
                            :key="prod.id"
                            type="button"
                            :disabled="prod.available_stock < 1"
                            @click.stop="addProductToCart(prod)"
                            class="flex flex-col justify-between rounded-lg border p-3 text-left transition"
                            :class="
                                prod.available_stock > 0
                                    ? 'border-gray-200 bg-white hover:border-red-400 hover:bg-red-50/30'
                                    : 'cursor-not-allowed border-gray-200 bg-gray-50 opacity-60'
                            "
                        >
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-[11px] font-bold text-gray-500">
                                        {{ prod.barcode }}
                                    </span>
                                    <span
                                        class="rounded-full px-2 py-0.5 font-mono text-[10px] font-extrabold"
                                        :class="
                                            prod.available_stock > 0
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-red-100 text-red-800'
                                        "
                                    >
                                        Stok: {{ prod.available_stock }} {{ prod.unit }}
                                    </span>
                                </div>
                                <div class="mt-1 text-sm font-bold text-gray-900">
                                    {{ prod.name }}
                                </div>
                            </div>

                            <div class="mt-2 flex items-end justify-between border-t border-gray-100 pt-2">
                                <span class="text-[11px] text-gray-500">{{ prod.category_name || '-' }}</span>
                                <div class="text-right">
                                    <div
                                        v-if="selectedMember && prod.member_price !== null && prod.member_price !== undefined"
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <span class="font-mono text-[11px] text-gray-400 line-through">
                                            {{ formatRupiah(prod.selling_price) }}
                                        </span>
                                        <span class="font-mono text-sm font-extrabold text-emerald-700">
                                            {{ formatRupiah(prod.member_price) }}
                                        </span>
                                    </div>
                                    <template v-else>
                                        <div class="font-mono text-sm font-extrabold text-red-700">
                                            {{ formatRupiah(prod.selling_price) }}
                                        </div>
                                        <div
                                            v-if="prod.member_price !== null && prod.member_price !== undefined"
                                            class="font-mono text-[10px] font-semibold text-emerald-700"
                                        >
                                            Member: {{ formatRupiah(prod.member_price) }}
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel Kanan: Status Member Lintas Cabang + Keranjang Belanja -->
            <div class="flex flex-col gap-4 lg:col-span-5">
                <!-- Panel Identitas Member (FR-06) -->
                <div
                    class="rounded-xl border p-4 shadow-sm"
                    :class="
                        selectedMember
                            ? 'border-green-300 bg-green-50'
                            : 'border-gray-200 bg-white'
                    "
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-500">
                                Status Anggota KOPDES (Portabilitas Lintas Desa)
                            </div>
                            <template v-if="selectedMember">
                                <div class="mt-1 text-base font-extrabold text-green-900">
                                    {{ selectedMember.name }} — {{ selectedMember.home_branch_name }}
                                </div>
                                <div class="font-mono text-xs text-green-700">
                                    Kode Member: {{ selectedMember.member_code }}
                                </div>
                            </template>
                            <template v-else>
                                <div class="mt-1 text-sm font-bold text-gray-800">
                                    Pembeli Umum (Non-Member)
                                </div>
                                <div class="text-xs text-gray-500">
                                    Tekan <strong>F2</strong> untuk tap Kartu RFID Member dari desa mana pun
                                </div>
                            </template>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <button
                                type="button"
                                @click.stop="activeModal = 'member'"
                                class="rounded-md bg-red-700 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-red-800"
                            >
                                {{ selectedMember ? 'Ganti (F2)' : 'Tap Member (F2)' }}
                            </button>
                            <button
                                v-if="selectedMember"
                                type="button"
                                @click.stop="selectedMember = null"
                                class="rounded-md border border-red-300 bg-white px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-50"
                            >
                                Lepas Member
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Keranjang Belanja Reaktif (FR-11) -->
                <div class="flex flex-1 flex-col justify-between rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <div>
                        <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                            <div>
                                <h2 class="text-base font-extrabold text-gray-900">
                                    Keranjang Belanja ({{ totalItemsCount }} item)
                                </h2>
                                <div
                                    v-if="idempotencyKey"
                                    class="font-mono text-[10px] text-gray-400"
                                >
                                    IDEMPOTENCY: {{ idempotencyKey }}
                                </div>
                            </div>

                            <button
                                v-if="cartItems.length > 0"
                                type="button"
                                @click.stop="resetCartManually"
                                class="text-xs font-semibold text-red-600 hover:underline"
                            >
                                Kosongkan
                            </button>
                        </div>

                        <div
                            v-if="cartItems.length === 0"
                            class="py-12 text-center text-sm text-gray-400"
                        >
                            Keranjang masih kosong. Silakan pindai barcode produk atau klik produk di katalog.
                        </div>

                        <div v-else class="mt-3 max-h-[42vh] divide-y divide-gray-100 overflow-y-auto">
                            <div
                                v-for="item in cartItems"
                                :key="item.product_id"
                                class="flex items-center justify-between gap-2 py-2.5"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-bold text-gray-900">
                                        {{ item.name }}
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 font-mono text-xs">
                                        <template v-if="isItemMemberPriceApplied(item) && Number(item.member_price) < Number(item.selling_price)">
                                            <span class="text-gray-400 line-through">
                                                {{ formatRupiah(item.selling_price) }}
                                            </span>
                                            <span class="font-bold text-emerald-700">
                                                {{ formatRupiah(item.member_price) }}
                                            </span>
                                            <span class="text-gray-500">/ {{ item.unit }}</span>
                                            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                                Hemat {{ formatRupiah(getItemMemberSavings(item)) }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span class="text-gray-500">
                                                {{ formatRupiah(getEffectiveUnitPrice(item)) }} / {{ item.unit }}
                                            </span>
                                        </template>
                                        <span class="text-gray-400">(Stok: {{ item.available_stock }})</span>
                                    </div>
                                </div>

                                <!-- Kontrol Kuantitas (- / Input / +) -->
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click.stop="updateItemQty(item, item.qty - 1)"
                                        class="flex h-7 w-7 items-center justify-center rounded border border-gray-300 bg-gray-50 font-bold text-gray-700 hover:bg-gray-100"
                                    >
                                        -
                                    </button>
                                    <input
                                        :value="item.qty"
                                        data-ignore-barcode-focus="true"
                                        type="number"
                                        min="1"
                                        :max="item.available_stock"
                                        @change="updateItemQty(item, $event.target.value)"
                                        class="h-7 w-14 rounded border-gray-300 p-1 text-center font-mono text-xs font-bold"
                                    />
                                    <button
                                        type="button"
                                        @click.stop="updateItemQty(item, item.qty + 1)"
                                        class="flex h-7 w-7 items-center justify-center rounded border border-gray-300 bg-gray-50 font-bold text-gray-700 hover:bg-gray-100"
                                    >
                                        +
                                    </button>
                                </div>

                                <div class="w-24 text-right font-mono text-sm font-extrabold text-gray-900">
                                    {{ formatRupiah(getEffectiveUnitPrice(item) * item.qty) }}
                                </div>

                                <button
                                    type="button"
                                    @click.stop="removeItemFromCart(item.product_id)"
                                    class="ms-1 text-xs font-bold text-red-500 hover:text-red-700"
                                    title="Hapus item"
                                >
                                    &times;
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Grand Total & Tombol Bayar (F9) -->
                    <div class="mt-4 border-t border-gray-200 pt-4">
                        <div
                            v-if="totalMemberSavings > 0"
                            class="mb-2.5 space-y-1 rounded-lg border border-emerald-200 bg-emerald-50/70 px-3 py-2 text-xs"
                        >
                            <div class="flex items-center justify-between text-gray-600">
                                <span>Subtotal Harga Normal</span>
                                <span class="font-mono font-semibold">
                                    {{ formatRupiah(regularSubtotal) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between font-bold text-emerald-800">
                                <span>Total Hemat Member</span>
                                <span class="font-mono">
                                    -{{ formatRupiah(totalMemberSavings) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-600">GRAND TOTAL</span>
                            <span class="font-mono text-2xl font-black text-red-700">
                                {{ formatRupiah(grandTotal) }}
                            </span>
                        </div>

                        <button
                            type="button"
                            :disabled="cartItems.length === 0 || isSubmitting"
                            @click.stop="
                                paymentError = '';
                                activeModal = 'payment';
                            "
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-red-700 py-3.5 text-base font-extrabold text-white shadow-md transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <span>PROSES PEMBAYARAN</span>
                            <kbd class="rounded bg-red-900 px-2 py-0.5 font-mono text-xs">F9</kbd>
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal F2: Tap Kartu Member Lintas Cabang -->
        <MemberLookupModal
            :show="activeModal === 'member'"
            :selected-member="selectedMember"
            @close="activeModal = null"
            @selected="
                (member) => {
                    selectedMember = member;
                    showNotification(
                        'success',
                        `Member tertaut: ${member.name} — ${member.home_branch_name}`,
                    );
                }
            "
            @detach="
                () => {
                    selectedMember = null;
                    showNotification('info', 'Tautan member dilepas (kembali ke Pembeli Umum).');
                }
            "
        />

        <!-- Modal F9: Pembayaran Tunai & Midtrans Snap -->
        <PaymentModal
            :show="activeModal === 'payment'"
            :total-amount="grandTotal"
            :regular-subtotal="regularSubtotal"
            :member-discount-total="totalMemberSavings"
            :items-count="totalItemsCount"
            :selected-member="selectedMember"
            :is-submitting="isSubmitting"
            :error-message="paymentError"
            @close="activeModal = null"
            @submit="handleCheckoutSubmit"
        />

        <!-- Drawer Transaksi Pending & Riwayat Hari Ini -->
        <PendingTransactionsDrawer
            :show="activeModal === 'drawer'"
            :pending-transactions="pendingTransactions"
            :today-transactions="todayTransactions"
            :processing-id="processingTxId"
            @close="activeModal = null"
            @refresh="refreshTransactionsLists"
            @reopen-snap="(tx) => openSnapPopup(tx)"
            @check-status="(tx) => handleCheckTransactionStatus(tx, true)"
            @cancel-transaction="(tx) => handleCancelTransaction(tx)"
            @reprint="(tx) => triggerThermalPrint(tx)"
        />

        <!-- Modal Wajib Buka Shift Kasir -->
        <OpenShiftModal
            :show="!isLocked && (!currentShift || activeModal === 'open-shift') && activeModal !== 'close-shift'"
            :branch="branch"
            :cashier="currentCashier"
            @opened="handleShiftOpened"
            @logout="handleLogout"
        />

        <!-- Modal F4: Mutasi Kas Laci (Kas Masuk / Kas Keluar) -->
        <ShiftCashMovementModal
            :show="activeModal === 'cash-movement'"
            :shift="currentShift"
            @close="activeModal = null"
            @recorded="handleCashMovementRecorded"
        />

        <!-- Modal F10: Tutup Shift Blind Count -->
        <CloseShiftModal
            :show="activeModal === 'close-shift'"
            :shift="currentShift"
            :pending-transactions="shiftPendingTransactions"
            @close="activeModal = null"
            @closed="handleShiftClosed"
            @open-pending-drawer="
                refreshTransactionsLists();
                activeModal = 'drawer';
            "
            @print-closing="triggerShiftClosingPrint"
            @open-new-shift="activeModal = 'open-shift'"
            @logout="handleLogout"
        />

        <!-- Modal F12 / Idle 15m: Kunci Layar POS -->
        <PosLockModal
            :show="isLocked || activeModal === 'lock'"
            :current-cashier="currentCashier"
            :branch="branch"
            @unlocked="handlePosUnlocked"
        />

        <!-- Simulator Hardware POS (Hanya dirender bila APP_DEMO_MODE=true) -->
        <HardwareSimulatorPopover
            v-if="isDemoMode && currentShift && !isLocked"
            @scan-barcode="simulateBarcodeScan"
            @tap-member="simulateMemberTap"
        />
    </div>

    <!-- Komponen Cetak Struk Thermal Transaksi (@media print 58mm / 80mm) -->
    <ReceiptThermal
        :transaction="activeReceiptTransaction"
        :branch="branch"
        :paper-width="paperWidth"
    />

    <!-- Komponen Cetak Struk Thermal Tutup Shift (@media print 58mm / 80mm) -->
    <ShiftClosingReceipt
        :shift="activeShiftClosingReceipt"
        :branch="branch"
        :paper-width="paperWidth"
    />
</template>

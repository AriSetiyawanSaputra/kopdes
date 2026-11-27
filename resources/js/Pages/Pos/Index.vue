<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
const selectedCategory = ref('ALL');
const barcodeInputRef = ref(null);
const isBarcodeFocused = ref(false);

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

const categoryTabs = computed(() => {
    const names = new Set();
    catalogProducts.value.forEach((p) => {
        if (p.category_name) {
            names.add(p.category_name);
        }
    });
    return ['ALL', ...Array.from(names).sort()];
});

const filteredCatalog = computed(() => {
    const q = catalogSearch.value.trim().toLowerCase();

    return catalogProducts.value.filter((p) => {
        const matchesCat =
            selectedCategory.value === 'ALL' || p.category_name === selectedCategory.value;
        if (!matchesCat) {
            return false;
        }
        if (!q) {
            return true;
        }
        return (
            p.name.toLowerCase().includes(q) ||
            p.sku.toLowerCase().includes(q) ||
            p.barcode.toLowerCase().includes(q)
        );
    });
});

const cartQtyByProductId = computed(() => {
    const map = {};
    cartItems.value.forEach((item) => {
        map[item.product_id] = item.qty;
    });
    return map;
});

const isCrossBranchMember = computed(() => {
    if (!selectedMember.value) {
        return false;
    }
    return Number(selectedMember.value.home_branch_id) !== Number(props.branch.id);
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

    <!-- Area Layar Utama POS (Terisolasi penuh dari @media print) -->
    <div
        class="no-print print:hidden flex min-h-screen flex-col bg-slate-100 text-slate-900 lg:h-screen lg:flex-row lg:overflow-hidden"
        @click="focusBarcodeInputIfIdle"
    >
        <!-- ============================================================ -->
        <!-- PANEL KIRI (~62%): Header POS, Scanner Barcode & Katalog      -->
        <!-- ============================================================ -->
        <section class="flex min-w-0 flex-1 flex-col lg:h-full lg:w-[62%] lg:flex-none lg:overflow-hidden">
            <!-- Header POS Kompak -->
            <header class="shrink-0 border-b border-slate-200 bg-white px-4 py-3 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-2.5">
                    <!-- Brand, Cabang, Kasir & Shift Status -->
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-kopdes-600 to-kopdes-800 text-white shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="truncate text-sm font-extrabold tracking-tight text-slate-900 sm:text-base">
                                    {{ branch.name }}
                                </h1>
                                <span class="kopdes-num rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                                    {{ branch.code }}
                                </span>
                                <StatusBadge v-if="isDemoMode" variant="amber" size="xs" dot pulse>
                                    Demo
                                </StatusBadge>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span>Kasir: <strong class="font-semibold text-slate-800">{{ currentCashier.name }}</strong></span>
                                <span aria-hidden="true">&bull;</span>
                                <StatusBadge v-if="currentShift" variant="emerald" size="xs" dot>
                                    <span class="kopdes-num">{{ currentShift.shift_code }}</span>
                                </StatusBadge>
                                <StatusBadge v-else variant="amber" size="xs" dot>
                                    Shift Belum Dibuka
                                </StatusBadge>
                            </div>
                        </div>
                    </div>

                    <!-- Toolbar Aksi POS & Shortcut Badges -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <!-- Toggle Kertas Thermal 58mm / 80mm -->
                        <div
                            class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-100 p-0.5 text-[11px]"
                            title="Lebar Kertas Thermal"
                        >
                            <button
                                type="button"
                                @click.stop="changePaperWidth('58mm')"
                                class="kopdes-num cursor-pointer rounded-lg px-2 py-1 font-bold transition-colors"
                                :class="
                                    paperWidth === '58mm'
                                        ? 'bg-white text-kopdes-700 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-800'
                                "
                            >
                                58mm
                            </button>
                            <button
                                type="button"
                                @click.stop="changePaperWidth('80mm')"
                                class="kopdes-num cursor-pointer rounded-lg px-2 py-1 font-bold transition-colors"
                                :class="
                                    paperWidth === '80mm'
                                        ? 'bg-white text-kopdes-700 shadow-sm'
                                        : 'text-slate-500 hover:text-slate-800'
                                "
                            >
                                80mm
                            </button>
                        </div>

                        <!-- Shift Controls -->
                        <button
                            v-if="!currentShift"
                            type="button"
                            @click.stop="activeModal = 'open-shift'"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-amber-500 px-3 py-1.5 text-xs font-extrabold text-slate-950 shadow-sm transition-colors hover:bg-amber-400"
                        >
                            Buka Shift
                        </button>

                        <template v-else>
                            <button
                                type="button"
                                @click.stop="activeModal = 'cash-movement'"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50"
                            >
                                <span>Kas Laci</span>
                                <kbd class="kopdes-num rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-slate-200">F4</kbd>
                            </button>

                            <button
                                type="button"
                                @click.stop="activeModal = 'close-shift'"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-800 transition-colors hover:bg-emerald-100"
                            >
                                <span>Tutup Shift</span>
                                <kbd class="kopdes-num rounded bg-emerald-700 px-1.5 py-0.5 text-[10px] font-bold text-white">F10</kbd>
                            </button>
                        </template>

                        <!-- Label Barcode -->
                        <Link
                            :href="route('products.barcode-labels')"
                            class="hidden cursor-pointer items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 xl:inline-flex"
                            title="Cetak Stiker Label Barcode Produk"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <span>Barcode</span>
                        </Link>

                        <!-- Pending & Riwayat Drawer Button -->
                        <button
                            type="button"
                            @click.stop="
                                refreshTransactionsLists();
                                activeModal = 'drawer';
                            "
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-bold text-indigo-800 transition-colors hover:bg-indigo-100"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Pending</span>
                            <span
                                v-if="pendingTransactions.length > 0"
                                class="kopdes-num inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-black text-slate-950"
                            >
                                {{ pendingTransactions.length }}
                            </span>
                        </button>

                        <!-- Quick Lock F12 -->
                        <button
                            type="button"
                            @click.stop="lockPosSession"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-900 transition-colors hover:bg-amber-100"
                            title="Kunci Layar Cepat (F12)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span class="hidden sm:inline">Kunci</span>
                            <kbd class="kopdes-num rounded bg-amber-600 px-1.5 py-0.5 text-[10px] font-bold text-white">F12</kbd>
                        </button>

                        <!-- Logout -->
                        <button
                            type="button"
                            @click.stop="handleLogout"
                            class="cursor-pointer rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition-colors hover:border-rose-200 hover:bg-rose-50"
                            title="Keluar Sesi Kasir"
                        >
                            Keluar
                        </button>
                    </div>
                </div>
            </header>

            <!-- Left Scrollable Body -->
            <div class="flex min-h-0 flex-1 flex-col gap-3.5 p-4 lg:overflow-hidden">
                <!-- Inline Alert Toast Banner -->
                <div
                    v-if="alertBanner"
                    role="status"
                    class="animate-fade-in-up flex shrink-0 items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-xs font-semibold shadow-card"
                    :class="{
                        'border-rose-200 bg-rose-50 text-rose-900': alertBanner.type === 'error',
                        'border-amber-200 bg-amber-50 text-amber-900': alertBanner.type === 'warning',
                        'border-emerald-200 bg-emerald-50 text-emerald-900': alertBanner.type === 'success',
                        'border-indigo-200 bg-indigo-50 text-indigo-900': alertBanner.type === 'info',
                    }"
                >
                    <span>{{ alertBanner.message }}</span>
                    <button
                        type="button"
                        @click.stop="alertBanner = null"
                        class="shrink-0 cursor-pointer rounded-md px-2 py-0.5 text-[11px] font-bold underline hover:opacity-80"
                    >
                        Tutup
                    </button>
                </div>

                <!-- Bar Scan Barcode Besar + Indikator Fokus Visual -->
                <div
                    class="shrink-0 rounded-2xl border bg-white p-3.5 shadow-card transition-all duration-150"
                    :class="
                        activeModal === null && isBarcodeFocused
                            ? 'border-kopdes-500 ring-2 ring-kopdes-500/20'
                            : 'border-slate-200/90'
                    "
                >
                    <form @submit.prevent="handleBarcodeSubmit">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label
                                for="pos-barcode-scanner-input"
                                class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-slate-700"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-kopdes-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                                <span>Scan Barcode Produk</span>
                            </label>

                            <StatusBadge
                                :variant="activeModal === null && !isLocked ? 'emerald' : 'slate'"
                                size="xs"
                                dot
                                :pulse="activeModal === null && !isLocked && isBarcodeFocused"
                            >
                                {{
                                    activeModal === null && !isLocked
                                        ? isBarcodeFocused
                                            ? 'Scanner Fokus & Siap'
                                            : 'Scanner Siap'
                                        : 'Scanner Terjeda (Modal Aktif)'
                                }}
                            </StatusBadge>
                        </div>

                        <div class="mt-2 flex gap-2">
                            <div class="relative flex-1">
                                <input
                                    id="pos-barcode-scanner-input"
                                    ref="barcodeInputRef"
                                    v-model="barcodeQuery"
                                    type="text"
                                    autocomplete="off"
                                    :disabled="activeModal !== null || isLocked"
                                    @focus="isBarcodeFocused = true"
                                    @blur="isBarcodeFocused = false"
                                    class="kopdes-input kopdes-num py-3 pl-4 pr-10 text-base font-bold tracking-wide disabled:bg-slate-100"
                                    placeholder="Pindai barcode produk atau ketik kode lalu tekan Enter..."
                                />
                                <button
                                    v-if="barcodeQuery"
                                    type="button"
                                    @click.stop="barcodeQuery = ''; focusBarcodeInputIfIdle()"
                                    aria-label="Hapus input barcode"
                                    class="absolute inset-y-0 right-2.5 flex cursor-pointer items-center text-slate-400 hover:text-slate-700"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <button
                                type="submit"
                                :disabled="activeModal !== null || isScanningBarcode"
                                class="btn-kopdes-primary shrink-0 px-5 text-sm font-bold"
                            >
                                {{ isScanningBarcode ? 'Mencari...' : 'Tambah' }}
                            </button>
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-500">
                            Input ini khusus barcode produk. Untuk Kartu RFID Member, tekan
                            <kbd class="kopdes-num rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-700">F2</kbd>
                            terlebih dahulu.
                        </p>
                    </form>
                </div>

                <!-- Bar Simulator Hardware Demo (Collapsible, Hanya saat APP_DEMO_MODE=true) -->
                <HardwareSimulatorPopover
                    v-if="isDemoMode && currentShift && !isLocked"
                    class="shrink-0"
                    @scan-barcode="simulateBarcodeScan"
                    @tap-member="simulateMemberTap"
                />

                <!-- Katalog Cepat Produk Cabang -->
                <div class="kopdes-card flex min-h-0 flex-1 flex-col overflow-hidden">
                    <!-- Header Pencarian & Tab Kategori -->
                    <div class="shrink-0 space-y-2.5 border-b border-slate-200/80 bg-slate-50/70 px-4 py-3">
                        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-extrabold text-slate-900">
                                    Katalog Cepat Cabang
                                </h2>
                                <span class="kopdes-num rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                                    {{ filteredCatalog.length }} produk
                                </span>
                            </div>

                            <div class="relative sm:w-64">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    v-model="catalogSearch"
                                    data-ignore-barcode-focus="true"
                                    type="text"
                                    placeholder="Cari nama produk / SKU..."
                                    class="kopdes-input py-1.5 pl-8 pr-3 text-xs"
                                />
                            </div>
                        </div>

                        <!-- Category Filter Chips -->
                        <div
                            v-if="categoryTabs.length > 2"
                            class="flex items-center gap-1.5 overflow-x-auto pb-0.5"
                        >
                            <button
                                v-for="cat in categoryTabs"
                                :key="cat"
                                type="button"
                                @click.stop="selectedCategory = cat"
                                class="shrink-0 cursor-pointer rounded-full px-2.5 py-1 text-[11px] font-semibold transition-colors"
                                :class="
                                    selectedCategory === cat
                                        ? 'bg-kopdes-700 text-white shadow-sm'
                                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                "
                            >
                                {{ cat === 'ALL' ? 'Semua Kategori' : cat }}
                            </button>
                        </div>
                    </div>

                    <!-- Product Grid -->
                    <div class="flex-1 overflow-y-auto p-3.5">
                        <div
                            v-if="filteredCatalog.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-center"
                        >
                            <p class="text-sm font-bold text-slate-700">Produk tidak ditemukan</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Coba ubah kata kunci pencarian atau pilih kategori lain.
                            </p>
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3"
                        >
                            <button
                                v-for="prod in filteredCatalog"
                                :key="prod.id"
                                type="button"
                                :disabled="prod.available_stock < 1"
                                @click.stop="addProductToCart(prod)"
                                class="group relative flex flex-col justify-between rounded-xl border p-3 text-left transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-kopdes-500/30"
                                :class="
                                    prod.available_stock > 0
                                        ? cartQtyByProductId[prod.id]
                                            ? 'cursor-pointer border-kopdes-300 bg-kopdes-50/30 hover:border-kopdes-500'
                                            : 'cursor-pointer border-slate-200 bg-white hover:border-kopdes-300 hover:bg-slate-50/70'
                                        : 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-55'
                                "
                            >
                                <!-- Active Cart Quantity Indicator -->
                                <span
                                    v-if="cartQtyByProductId[prod.id]"
                                    class="kopdes-num absolute -right-1.5 -top-1.5 flex h-6 min-w-[24px] items-center justify-center rounded-full bg-kopdes-700 px-1.5 text-[11px] font-extrabold text-white shadow-sm ring-2 ring-white"
                                >
                                    {{ cartQtyByProductId[prod.id] }}
                                </span>

                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                            {{ prod.category_name || 'Umum' }}
                                        </span>
                                        <span
                                            class="kopdes-num shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                            :class="
                                                prod.available_stock <= 0
                                                    ? 'bg-rose-100 text-rose-700'
                                                    : prod.available_stock <= 5
                                                      ? 'bg-amber-100 text-amber-800'
                                                      : 'bg-emerald-50 text-emerald-700'
                                            "
                                        >
                                            {{ prod.available_stock > 0 ? `${prod.available_stock} ${prod.unit}` : 'Habis' }}
                                        </span>
                                    </div>

                                    <div class="mt-1.5 line-clamp-2 text-sm font-bold leading-snug text-slate-900">
                                        {{ prod.name }}
                                    </div>
                                    <div class="kopdes-num mt-0.5 text-[10px] text-slate-400">
                                        {{ prod.barcode }}
                                    </div>
                                </div>

                                <div class="mt-3 flex items-end justify-between border-t border-slate-100 pt-2">
                                    <span class="text-[10px] font-medium text-slate-400">/{{ prod.unit }}</span>
                                    <div class="text-right">
                                        <div
                                            v-if="selectedMember && prod.member_price !== null && prod.member_price !== undefined && Number(prod.member_price) < Number(prod.selling_price)"
                                            class="flex flex-col items-end"
                                        >
                                            <span class="kopdes-num text-[10px] text-slate-400 line-through">
                                                {{ formatRupiah(prod.selling_price) }}
                                            </span>
                                            <span class="kopdes-num text-sm font-extrabold text-emerald-700">
                                                {{ formatRupiah(prod.member_price) }}
                                            </span>
                                        </div>
                                        <template v-else>
                                            <div class="kopdes-num text-sm font-extrabold text-slate-900 group-hover:text-kopdes-700">
                                                {{ formatRupiah(prod.selling_price) }}
                                            </div>
                                            <div
                                                v-if="prod.member_price !== null && prod.member_price !== undefined && Number(prod.member_price) < Number(prod.selling_price)"
                                                class="kopdes-num text-[10px] font-semibold text-emerald-700"
                                            >
                                                Member {{ formatRupiah(prod.member_price) }}
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- PANEL KANAN (~38%): Identitas Pembeli, Keranjang & Grand Total -->
        <!-- ============================================================ -->
        <aside class="flex min-w-0 flex-col border-t border-slate-200 bg-white lg:h-full lg:w-[38%] lg:flex-none lg:overflow-hidden lg:border-l lg:border-t-0">
            <!-- Kartu Identitas Pembeli (Non-Member vs Member + Lintas Cabang Badge) -->
            <div class="shrink-0 border-b border-slate-200/90 p-4">
                <div
                    class="rounded-2xl border p-3.5 transition-colors"
                    :class="
                        selectedMember
                            ? 'border-emerald-200 bg-gradient-to-br from-emerald-50/90 to-white'
                            : 'border-slate-200 bg-slate-50/80'
                    "
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                                    Identitas Pembeli
                                </span>
                                <StatusBadge v-if="selectedMember" variant="emerald" size="xs" dot>
                                    Harga Member Aktif
                                </StatusBadge>
                                <StatusBadge v-if="isCrossBranchMember" variant="indigo" size="xs">
                                    Lintas Cabang
                                </StatusBadge>
                            </div>

                            <template v-if="selectedMember">
                                <div class="mt-1.5 truncate text-base font-extrabold text-slate-900">
                                    {{ selectedMember.name }}
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="kopdes-num font-semibold text-emerald-800">
                                        {{ selectedMember.member_code }}
                                    </span>
                                    <span class="text-slate-300">&bull;</span>
                                    <span class="truncate font-medium text-slate-600">
                                        Asal: <strong class="text-slate-800">{{ selectedMember.home_branch_name }}</strong>
                                    </span>
                                </div>
                            </template>

                            <template v-else>
                                <div class="mt-1 text-sm font-extrabold text-slate-800">
                                    Pembeli Umum (Non-Member)
                                </div>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    Tap Kartu RFID Member desa mana pun untuk harga khusus &amp; catat SHU.
                                </p>
                            </template>
                        </div>

                        <div class="flex shrink-0 flex-col gap-1.5">
                            <button
                                type="button"
                                @click.stop="activeModal = 'member'"
                                class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-kopdes-700 px-3 py-2 text-xs font-bold text-white shadow-sm transition-colors hover:bg-kopdes-800"
                            >
                                <span>{{ selectedMember ? 'Ganti' : 'Tap Member' }}</span>
                                <kbd class="kopdes-num rounded bg-kopdes-900 px-1.5 py-0.5 text-[10px] font-bold text-kopdes-100">F2</kbd>
                            </button>
                            <button
                                v-if="selectedMember"
                                type="button"
                                @click.stop="selectedMember = null"
                                class="cursor-pointer rounded-xl border border-rose-200 bg-white px-3 py-1 text-[11px] font-semibold text-rose-700 transition-colors hover:bg-rose-50"
                            >
                                Lepas Member
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header Keranjang Belanja -->
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-4 py-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-extrabold text-slate-900">
                        Keranjang Belanja
                    </h2>
                    <span class="kopdes-num rounded-full bg-kopdes-50 px-2 py-0.5 text-xs font-extrabold text-kopdes-700 ring-1 ring-kopdes-200/80">
                        {{ totalItemsCount }} item
                    </span>
                </div>

                <button
                    v-if="cartItems.length > 0"
                    type="button"
                    @click.stop="resetCartManually"
                    class="cursor-pointer rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 hover:text-rose-700"
                >
                    Kosongkan
                </button>
            </div>

            <!-- Daftar Item Keranjang (Scrollable) -->
            <div class="flex-1 overflow-y-auto px-4 py-2">
                <div
                    v-if="cartItems.length === 0"
                    class="flex h-full min-h-[220px] flex-col items-center justify-center px-6 py-10 text-center"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-bold text-slate-700">
                        Keranjang masih kosong
                    </p>
                    <p class="mt-1 max-w-xs text-xs leading-relaxed text-slate-500">
                        Pindai barcode produk di sebelah kiri atau klik kartu produk pada katalog cepat untuk memulai transaksi.
                    </p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="item in cartItems"
                        :key="item.product_id"
                        class="py-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-bold text-slate-900">
                                    {{ item.name }}
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                                    <template v-if="isItemMemberPriceApplied(item) && Number(item.member_price) < Number(item.selling_price)">
                                        <span class="kopdes-num text-slate-400 line-through">
                                            {{ formatRupiah(item.selling_price) }}
                                        </span>
                                        <span class="kopdes-num font-bold text-emerald-700">
                                            {{ formatRupiah(item.member_price) }}
                                        </span>
                                        <span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-200/70">
                                            Hemat {{ formatRupiah(getItemMemberSavings(item)) }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="kopdes-num font-medium text-slate-500">
                                            {{ formatRupiah(getEffectiveUnitPrice(item)) }} / {{ item.unit }}
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click.stop="removeItemFromCart(item.product_id)"
                                aria-label="Hapus item dari keranjang"
                                class="cursor-pointer rounded-lg p-1 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <!-- Tactile Qty Stepper + Subtotal -->
                        <div class="mt-2.5 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    @click.stop="updateItemQty(item, item.qty - 1)"
                                    aria-label="Kurangi jumlah"
                                    class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-white text-base font-extrabold text-slate-700 shadow-sm transition-colors hover:border-slate-400 hover:bg-slate-50 active:bg-slate-100"
                                >
                                    &minus;
                                </button>
                                <input
                                    :value="item.qty"
                                    data-ignore-barcode-focus="true"
                                    type="number"
                                    min="1"
                                    :max="item.available_stock"
                                    @change="updateItemQty(item, $event.target.value)"
                                    aria-label="Jumlah item"
                                    class="kopdes-num h-9 w-14 rounded-xl border-slate-300 p-1 text-center text-sm font-extrabold text-slate-900 shadow-sm focus:border-kopdes-600 focus:ring-kopdes-500/25"
                                />
                                <button
                                    type="button"
                                    @click.stop="updateItemQty(item, item.qty + 1)"
                                    aria-label="Tambah jumlah"
                                    class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-white text-base font-extrabold text-slate-700 shadow-sm transition-colors hover:border-kopdes-400 hover:bg-kopdes-50 hover:text-kopdes-700 active:bg-kopdes-100"
                                >
                                    +
                                </button>
                                <span class="kopdes-num ml-1 text-[11px] text-slate-400">
                                    maks {{ item.available_stock }}
                                </span>
                            </div>

                            <div class="kopdes-num text-right text-sm font-extrabold text-slate-900">
                                {{ formatRupiah(getEffectiveUnitPrice(item) * item.qty) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Ringkasan Grand Total & CTA Bayar (F9) -->
            <div class="shrink-0 border-t border-slate-200 bg-slate-50/90 p-4">
                <!-- Highlight Hijau Total Hemat Member -->
                <div
                    v-if="totalMemberSavings > 0"
                    class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5"
                >
                    <div class="flex items-center justify-between text-xs text-emerald-900/80">
                        <span>Subtotal Harga Normal</span>
                        <span class="kopdes-num font-semibold line-through">
                            {{ formatRupiah(regularSubtotal) }}
                        </span>
                    </div>
                    <div class="mt-1 flex items-center justify-between text-xs font-extrabold text-emerald-800">
                        <span class="inline-flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span>Total Hemat Member</span>
                        </span>
                        <span class="kopdes-num text-sm">
                            -{{ formatRupiah(totalMemberSavings) }}
                        </span>
                    </div>
                </div>

                <!-- Grand Total -->
                <div class="flex items-baseline justify-between gap-2">
                    <div>
                        <span class="block text-xs font-extrabold uppercase tracking-wider text-slate-500">
                            Grand Total
                        </span>
                        <span v-if="idempotencyKey" class="kopdes-num block text-[9px] text-slate-400">
                            Ref: {{ idempotencyKey.slice(0, 8) }}
                        </span>
                    </div>
                    <span class="kopdes-num text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                        {{ formatRupiah(grandTotal) }}
                    </span>
                </div>

                <!-- High-Contrast CTA Bayar (F9) -->
                <button
                    type="button"
                    :disabled="cartItems.length === 0 || isSubmitting"
                    @click.stop="
                        paymentError = '';
                        activeModal = 'payment';
                    "
                    class="mt-3.5 flex w-full cursor-pointer items-center justify-center gap-2.5 rounded-2xl bg-kopdes-700 px-5 py-4 text-base font-extrabold text-white shadow-md transition-colors duration-150 hover:bg-kopdes-800 focus:outline-none focus:ring-2 focus:ring-kopdes-500 focus:ring-offset-2 active:bg-kopdes-900 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <span>{{ isSubmitting ? 'Memproses...' : 'Bayar Sekarang' }}</span>
                    <kbd class="kopdes-num rounded-lg bg-kopdes-900/90 px-2 py-0.5 text-xs font-bold text-kopdes-100 ring-1 ring-white/15">
                        F9
                    </kbd>
                </button>
            </div>
        </aside>

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

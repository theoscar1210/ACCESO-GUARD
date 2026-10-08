import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';

/**
 * Ajusta la altura de un elemento al espacio visible que queda debajo de él,
 * para que la pantalla funcione sin scroll en celular, tablet y PC.
 */
export function useFitToViewport(
    el: Ref<HTMLElement | null>,
    { bottomGap = 12, minHeight = 360 } = {},
) {
    const height = ref<string>();

    function update() {
        if (!el.value) return;
        const top = el.value.getBoundingClientRect().top + window.scrollY;
        const available = window.innerHeight - top - bottomGap;
        height.value = `${Math.max(minHeight, available)}px`;
    }

    onMounted(() => {
        update();
        window.addEventListener('resize', update);
    });

    onBeforeUnmount(() => window.removeEventListener('resize', update));

    return { height, update };
}

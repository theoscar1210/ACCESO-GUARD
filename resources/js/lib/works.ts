/** Etiquetas y colores compartidos por las pantallas de obras */

export const workStatusLabel: Record<string, string> = {
    pendiente: 'Pendiente',
    aprobada: 'Aprobada',
    rechazada: 'Rechazada',
    suspendida: 'Suspendida',
    cerrada: 'Cerrada',
};

export const workStatusClass: Record<string, string> = {
    pendiente: 'bg-amber-100 text-amber-800',
    aprobada: 'bg-emerald-100 text-emerald-800',
    rechazada: 'bg-red-100 text-red-700',
    suspendida: 'bg-orange-100 text-orange-800',
    cerrada: 'bg-slate-200 text-slate-700',
};

export const workTypeLabel: Record<string, string> = {
    locativa: 'Arreglo locativo',
    construccion: 'Construcción',
};

export const workLogLabel: Record<string, string> = {
    creada: 'Creó la obra',
    editada: 'Editó los datos',
    aprobada: 'Aprobó',
    rechazada: 'Rechazó',
    suspendida: 'Suspendió',
    cerrada: 'Cerró',
    reabierta: 'Reabrió',
    trabajador_agregado: 'Agregó trabajador',
    trabajador_quitado: 'Quitó trabajador',
    salida_material_solicitada: 'Solicitó salida de material',
    salida_material_aprobada: 'Aprobó salida de material',
    salida_material_rechazada: 'Rechazó salida de material',
    salida_material_vencida: 'Venció salida de material (48 h sin retiro)',
};

export const materialReasonLabel: Record<string, string> = {
    sobrante: 'Sobrante',
    escombro: 'Escombro',
    devolucion: 'Devolución',
    otro: 'Otro',
};

export const materialStatusLabel: Record<string, string> = {
    pendiente: 'Pendiente',
    aprobada: 'Aprobada',
    rechazada: 'Rechazada',
    ejecutada: 'Retirado',
    vencida: 'Vencida',
};

export const materialStatusClass: Record<string, string> = {
    pendiente: 'bg-amber-100 text-amber-800',
    aprobada: 'bg-emerald-100 text-emerald-800',
    rechazada: 'bg-red-100 text-red-700',
    ejecutada: 'bg-slate-200 text-slate-700',
    vencida: 'bg-orange-100 text-orange-800',
};

export const movementLabel: Record<string, string> = {
    ingreso: 'Ingresó',
    salida: 'Salió',
    queda: 'Quedó en la casa',
};

export interface WorkItemInfo {
    id: number;
    name: string;
    serial: string | null;
    kind: 'herramienta' | 'material';
    quantity: number;
    owner: string | null;
    photo_url: string | null;
}

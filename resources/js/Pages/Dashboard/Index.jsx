import { Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index() {
    const user = usePage().props.auth.user;
    const isAdmin = user.role === 'admin';
    const destination = isAdmin ? '/admin/users' : '/dashboard/posts';
    const destinationLabel = isAdmin ? 'Panel Admin' : 'Artikel Saya';

    return (
        <AppLayout mode="dashboard" title="Dashboard">
            <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p className="text-sm font-medium uppercase tracking-wide text-indigo-700">Dashboard</p>
                <h1 className="mt-2 text-2xl font-bold text-slate-900">Selamat datang, {user.name}</h1>
                <p className="mt-2 text-slate-600">
                    Pilih area kerja untuk melanjutkan aktivitas Anda.
                </p>
                <Link
                    href={destination}
                    className="mt-6 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Buka {destinationLabel}
                </Link>
            </section>
        </AppLayout>
    );
}

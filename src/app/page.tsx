import Link from "next/link";

const modules = [
  { name: "Companies", href: "/companies", description: "Manage business entities" },
  { name: "Contacts", href: "/contacts", description: "Customers, suppliers & leads" },
  { name: "Products", href: "/products", description: "Product catalog & inventory" },
  { name: "Warehouses", href: "/warehouses", description: "Storage locations" },
  { name: "Sales Orders", href: "/sales-orders", description: "Customer orders & invoicing" },
  {
    name: "Purchase Orders",
    href: "/purchase-orders",
    description: "Supplier orders & procurement",
  },
];

export default function Home() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <header className="mb-12">
        <h1 className="text-3xl font-bold tracking-tight text-gray-900">Nexi ERP</h1>
        <p className="mt-2 text-lg text-gray-600">Enterprise Resource Planning System</p>
      </header>

      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {modules.map((mod) => (
          <Link
            key={mod.href}
            href={mod.href}
            className="group rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md"
          >
            <h2 className="text-lg font-semibold text-gray-900 group-hover:text-blue-600">
              {mod.name}
            </h2>
            <p className="mt-1 text-sm text-gray-500">{mod.description}</p>
          </Link>
        ))}
      </div>
    </div>
  );
}

import { PieChart, Pie, Cell, ResponsiveContainer, Legend, Tooltip } from 'recharts';

interface DonutChartProps {
    data: Array<{ name: string; value: number; color: string }>;
    title?: string;
    className?: string;
}

export default function DonutChart({ data, title, className = "" }: DonutChartProps) {
    const total = data.reduce((sum, item) => sum + item.value, 0);

    const renderCustomLabel = ({ cx, cy }: any) => {
        return (
            <text x={cx} y={cy} textAnchor="middle" dominantBaseline="middle" className="text-2xl font-bold fill-current">
                {total}
            </text>
        );
    };

    const renderTooltip = ({ active, payload }: any) => {
        if (active && payload && payload.length) {
            const data = payload[0];
            const percentage = total > 0 ? ((data.value / total) * 100).toFixed(1) : 0;
            return (
                <div className="bg-white p-3 border border-gray-200 rounded-lg shadow-lg">
                    <p className="font-medium">{data.name}</p>
                    <p className="text-sm text-gray-600">
                        {data.value} ({percentage}%)
                    </p>
                </div>
            );
        }
        return null;
    };

    return (
        <div className={`w-full ${className}`}>
            {title && (
                <h3 className="text-lg font-semibold mb-4 text-center">{title}</h3>
            )}
            <ResponsiveContainer width="100%" height={250}>
                <PieChart>
                    <Pie
                        data={data}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={renderCustomLabel}
                        outerRadius={80}
                        innerRadius={50}
                        fill="#8884d8"
                        dataKey="value"
                    >
                        {data.map((entry, index) => (
                            <Cell key={`cell-${index}`} fill={entry.color} />
                        ))}
                    </Pie>
                    <Tooltip content={renderTooltip} />
                    <Legend
                        verticalAlign="bottom"
                        height={36}
                        formatter={(value, entry) => (
                            <span style={{ color: entry.color }}>{value}</span>
                        )}
                    />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}

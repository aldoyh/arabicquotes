import { motion } from 'framer-motion';

interface ShinyTextProps {
  text: string;
  className?: string;
}

export const ShinyText = ({ text, className = '' }: ShinyTextProps) => {
  return (
    <motion.div
      className={`inline-block ${className}`}
      initial={{ backgroundPosition: '200% center' }}
      animate={{ backgroundPosition: '-200% center' }}
      transition={{
        duration: 3,
        repeat: Infinity,
        ease: 'linear',
      }}
      style={{
        background: 'linear-gradient(100deg, #64CEFB 0%, #64CEFB 20%, #ffffff 50%, #64CEFB 80%, #64CEFB 100%)',
        backgroundSize: '200% auto',
        WebkitBackgroundClip: 'text',
        backgroundClip: 'text',
        WebkitTextFillColor: 'transparent',
      }}
    >
      {text}
    </motion.div>
  );
};

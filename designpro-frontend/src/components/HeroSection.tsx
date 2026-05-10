import { motion } from 'framer-motion';
import { ArrowRight } from 'lucide-react';
import { ShinyText } from './ShinyText';
import { Navigation } from './Navigation';

export const HeroSection = () => {
  return (
    <div className="relative w-full h-screen overflow-hidden bg-black">
      {/* Video Background */}
      <video
        autoPlay
        loop
        muted
        playsInline
        className="absolute inset-0 w-full h-full object-cover"
      >
        <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260328_105406_16f4600d-7a92-4292-b96e-b19156c7830a.mp4" type="video/mp4" />
      </video>

      {/* Dark Overlay */}
      <div className="absolute inset-0 bg-black/50" />

      {/* Content */}
      <div className="relative z-10 w-full h-full flex flex-col">
        {/* Navigation */}
        <Navigation />

        {/* Main Content */}
        <div className="flex-1 flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 pt-20">
          {/* Top Section - Two Column Layout */}
          <div className="w-full max-w-7xl mb-12">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16">
              {/* Left Column */}
              <motion.p
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.8, delay: 0.2 }}
                className="text-white/80 text-sm lg:text-base leading-relaxed"
              >
                We deliver transformative programs that empower emerging product designers with cutting-edge expertise and vision to thrive globally.
              </motion.p>

              {/* Right Column */}
              <motion.p
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.8, delay: 0.3 }}
                className="text-white/80 text-sm lg:text-base text-right"
              >
                8000+ Talented Designers Launched !
              </motion.p>
            </div>
          </div>

          {/* Hero Section - Center */}
          <div className="w-full max-w-7xl text-center">
            {/* Small Uppercase Text */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ duration: 0.8, delay: 0.4 }}
              className="mb-8"
            >
              <p className="text-white/80 text-xs lg:text-sm uppercase tracking-tight">
                Seats for Next Program Opening Soon
              </p>
            </motion.div>

            {/* Main Heading with Shiny Effect */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.5 }}
              className="mb-12"
            >
              <div className="leading-none lg:leading-none" style={{ lineHeight: '0.85' }}>
                <h1 className="text-5xl sm:text-6xl md:text-7xl lg:text-8xl xl:text-9xl font-medium text-white tracking-tighter">
                  Become
                </h1>
                <h1 className="text-5xl sm:text-6xl md:text-7xl lg:text-8xl xl:text-9xl font-medium tracking-tighter">
                  <ShinyText text="Product Leader." className="text-5xl sm:text-6xl md:text-7xl lg:text-8xl xl:text-9xl font-medium" />
                </h1>
              </div>
            </motion.div>

            {/* CTA Button */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.6 }}
            >
              <button className="group flex items-center gap-3 mx-auto px-6 md:px-8 py-3 md:py-4 bg-black hover:bg-gray-900 text-white rounded-full transition-colors">
                Apply for Next Enrollment
                <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
              </button>
            </motion.div>
          </div>
        </div>
      </div>
    </div>
  );
};
